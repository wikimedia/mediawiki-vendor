<?php
namespace SmashPig\PaymentProviders\Ingenico\Tests;

use Psr\Cache\CacheItemPoolInterface;
use SmashPig\Core\Cache\SimpleCacheItem;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Ingenico\BankPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class BankPaymentProviderTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var BankPaymentProvider
	 */
	protected $provider;

	/**
	 * @var CacheItemPoolInterface
	 */
	protected $cache;

	public function setUp(): void {
		parent::setUp();

		$this->setProviderConfiguration( 'ingenico' );

		$globalConfig = Context::get()->getGlobalConfiguration();
		$this->cache = $globalConfig->object( 'cache', true );
		$this->cache->clear();

		$this->provider = new BankPaymentProvider( [
			'cache-parameters' => [
				'duration' => 10,
				'key-base' => 'BLAH_BLAH'
			]
		] );
	}

	public function testGetBankList() {
		$this->setUpResponse( __DIR__ . '/../Data/productDirectory.response', 200 );
		$results = $this->provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals(
			[
				'INGBNL2A' => 'Issuer Simulation V3 - ING'
			],
			$results
		);
	}

	public function testCacheBankList() {
		$this->setUpResponse( __DIR__ . '/../Data/productDirectory.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' );
		$results = $this->provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals(
			[
				'INGBNL2A' => 'Issuer Simulation V3 - ING'
			],
			$results
		);
		$cachedResults = $this->provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals( $results, $cachedResults );
	}

	/**
	 * When the lookup returns 404 we should cache the emptiness
	 */
	public function testCacheEmptyBankList() {
		$this->setUpResponse( __DIR__ . '/../Data/emptyDirectory.response', 404 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' );
		$results = $this->provider->getBankList( 'NL', 'COP' );
		$this->assertEquals( [], $results );
		$cached = $this->cache->getItem( 'BLAH_BLAH_NL_COP_809' );
		$this->assertTrue( $cached->isHit() );
		$again = $this->provider->getBankList( 'NL', 'COP' );
		$this->assertEquals( $results, $again );
	}

	public function testBustedCacheExpiration() {
		$cacheItem = new SimpleCacheItem(
			'BLAH_BLAH_NL_EUR_809',
			[
				'value' => [ 'STALE' => 'NotValid' ],
				'expiration' => time() - 100
			],
			true
		);
		$this->cache->save( $cacheItem );
		$this->setUpResponse( __DIR__ . '/../Data/productDirectory.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' );
		$results = $this->provider->getBankList( 'NL', 'EUR' );
		$this->assertEquals(
			[
				'INGBNL2A' => 'Issuer Simulation V3 - ING'
			],
			$results
		);
	}
}
