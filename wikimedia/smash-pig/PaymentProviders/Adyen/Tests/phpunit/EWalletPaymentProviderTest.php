<?php
namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Adyen\EWalletPaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 */
class EWalletPaymentProviderTest extends BaseAdyenTestCase {

	/**
	 * @var EWalletPaymentProvider
	 */
	public $provider;

	public function setUp(): void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/ew' );
	}

	public function testGetHostedPaymentDetailsCancelled() {
		$redirectResult = 'askjdfhakjsdfhaksdjfhaksdjfh';
		$this->mockApi->expects( $this->once() )
			->method( 'getPaymentDetails' )
			->with( $redirectResult )
			->willReturn( json_decode(
				file_get_contents( __DIR__ . '/../Data/getHostedPaymentDetails_vipps.json' ), true
			) );
		$result = $this->provider->getHostedPaymentDetails( $redirectResult );
		$this->assertEquals( FinalStatus::PENDING, $result->getStatus() );
	}
}
