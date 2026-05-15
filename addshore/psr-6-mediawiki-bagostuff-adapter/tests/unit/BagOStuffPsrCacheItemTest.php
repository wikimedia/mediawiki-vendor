<?php

namespace Addshore\Psr\Cache\MWBagOStuffAdapter\Tests;

use Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCacheItem;
use DateInterval;
use PHPUnit\Framework\TestCase;

class BagOStuffPsrCacheItemTest extends TestCase {

	public function testGetKeyAndHitState(): void {
		$item = new BagOStuffPsrCacheItem( 'my-key', 'value', true );

		$this->assertSame( 'my-key', $item->getKey() );
		$this->assertTrue( $item->isHit() );
		$this->assertSame( 'value', $item->get() );
	}

	public function testSetUpdatesValueAndReturnsSelf(): void {
		$item = new BagOStuffPsrCacheItem( 'my-key', null, false );

		$returned = $item->set( 'new-value' );

		$this->assertSame( $item, $returned );
		$this->assertSame( 'new-value', $item->get() );
	}

	public function testExpiresAtSetsExactExpiration(): void {
		$item = new BagOStuffPsrCacheItem( 'exp-key', 'value', true );
		$expiration = new \DateTimeImmutable( '+5 minutes' );

		$item->expiresAt( $expiration );

		$this->assertSame( $expiration, $item->getExpiration() );
	}

	public function testExpiresAfterWithIntervalSetsExpiration(): void {
		$item = new BagOStuffPsrCacheItem( 'exp-after', 'value', true );

		$item->expiresAfter( new DateInterval( 'PT10S' ) );

		$this->assertNotNull( $item->getExpiration() );
	}

	public function testExpiresAfterWithNullClearsExpiration(): void {
		$item = new BagOStuffPsrCacheItem( 'exp-null', 'value', true );
		$item->expiresAfter( new DateInterval( 'PT10S' ) );

		$returned = $item->expiresAfter( null );

		$this->assertSame( $item, $returned );
		$this->assertNull( $item->getExpiration() );
	}

}