<?php

namespace Addshore\Psr\Cache\MWBagOStuffAdapter\Tests;

use Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCache;
use Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCacheException;
use Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCacheInvalidArgumentException;
use Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCacheItem;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Wikimedia\ObjectCache\HashBagOStuff;

class BagOStuffPsrCacheTest extends TestCase {

	private BagOStuffPsrCache $cache;

	protected function setUp(): void {
		$this->cache = new BagOStuffPsrCache( new HashBagOStuff() );
	}

	public function testGetItemOnCacheMissReturnsNotHit(): void {
		$item = $this->cache->getItem( 'missing-key' );

		$this->assertFalse( $item->isHit() );
		$this->assertNull( $item->get() );
	}

	public function testSaveAndGetRoundTrip(): void {
		$item = new BagOStuffPsrCacheItem( 'known-key', null, false );
		$item->set( [ 'abc' => 123 ] );

		$this->assertTrue( $this->cache->save( $item ) );

		$stored = $this->cache->getItem( 'known-key' );
		$this->assertTrue( $stored->isHit() );
		$this->assertSame( [ 'abc' => 123 ], $stored->get() );
	}

	public function testSaveFalseThrowsException(): void {
		$item = new BagOStuffPsrCacheItem( 'false-key', null, false );
		$item->set( false );

		$this->expectException( BagOStuffPsrCacheException::class );
		$this->cache->save( $item );
	}

	public function testSaveRejectsForeignCacheItemImplementation(): void {
		$foreignItem = $this->createMock( CacheItemInterface::class );

		$this->expectException( BagOStuffPsrCacheInvalidArgumentException::class );
		$this->cache->save( $foreignItem );
	}

	public function testDeleteItemRemovesValue(): void {
		$item = new BagOStuffPsrCacheItem( 'delete-key', null, false );
		$item->set( 'to-delete' );
		$this->cache->save( $item );

		$this->assertTrue( $this->cache->hasItem( 'delete-key' ) );
		$this->assertTrue( $this->cache->deleteItem( 'delete-key' ) );
		$this->assertFalse( $this->cache->hasItem( 'delete-key' ) );
	}

	public function testClearRemovesAllSavedValues(): void {
		$itemA = new BagOStuffPsrCacheItem( 'key-a', null, false );
		$itemA->set( 'value-a' );
		$itemB = new BagOStuffPsrCacheItem( 'key-b', null, false );
		$itemB->set( 'value-b' );

		$this->cache->save( $itemA );
		$this->cache->save( $itemB );

		$this->assertTrue( $this->cache->clear() );
		$this->assertFalse( $this->cache->hasItem( 'key-a' ) );
		$this->assertFalse( $this->cache->hasItem( 'key-b' ) );
	}

	public function testReservedCharactersInKeyThrow(): void {
		$this->expectException( BagOStuffPsrCacheInvalidArgumentException::class );
		$this->cache->getItem( 'bad{key' );
	}

}