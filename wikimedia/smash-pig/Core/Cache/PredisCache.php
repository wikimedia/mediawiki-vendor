<?php

namespace SmashPig\Core\Cache;

use Predis\Client;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;

class PredisCache implements CacheItemPoolInterface {

	/** @var Client */
	protected $client;

	protected $deferredQueue = [];

	protected $prefix = 'smashpig_cache_';

	public function __construct( array $options ) {
		if ( empty( $options['servers'] ) ) {
			throw new \Exception( 'PredisCache constructor needs a "servers" key' );
		}
		$this->client = new Client( $options['servers'] );
		if ( !empty( $options['prefix'] ) ) {
			$this->prefix = $options['prefix'];
		}
	}

	protected function fullKey( string $key ): string {
		return $this->prefix . $key;
	}

	/**
	 * Returns a Cache Item representing the specified key.
	 *
	 * This method must always return a CacheItemInterface object, even in case of
	 * a cache miss. It MUST NOT return null.
	 *
	 * @param string $key
	 *   The key for which to return the corresponding Cache Item.
	 *
	 * @throws InvalidArgumentException
	 *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
	 *   MUST be thrown.
	 *
	 * @return CacheItemInterface
	 *   The corresponding Cache Item.
	 */
	public function getItem( $key ) {
		$fullKey = $this->fullKey( $key );
		if ( $this->client->exists( $fullKey ) ) {
			return new SimpleCacheItem( $key, json_decode( $this->client->get( $fullKey ), true ), true );
		}
		return new SimpleCacheItem( $key, null, false );
	}

	/**
	 * Returns a traversable set of cache items.
	 *
	 * @param string[] $keys
	 *   An indexed array of keys of items to retrieve.
	 *
	 * @throws InvalidArgumentException
	 *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
	 *   MUST be thrown.
	 *
	 * @return array|\Traversable
	 *   A traversable collection of Cache Items keyed by the cache keys of
	 *   each item. A Cache item will be returned for each key, even if that
	 *   key is not found. However, if no keys are specified then an empty
	 *   traversable MUST be returned instead.
	 */
	public function getItems( array $keys = [] ) {
		return array_map( [ $this, 'getItem' ], $keys );
	}

	/**
	 * Confirms if the cache contains specified cache item.
	 *
	 * Note: This method MAY avoid retrieving the cached value for performance reasons.
	 * This could result in a race condition with CacheItemInterface::get(). To avoid
	 * such situation use CacheItemInterface::isHit() instead.
	 *
	 * @param string $key
	 *   The key for which to check existence.
	 *
	 * @throws InvalidArgumentException
	 *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
	 *   MUST be thrown.
	 *
	 * @return bool
	 *   True if item exists in the cache, false otherwise.
	 */
	public function hasItem( $key ) {
		return $this->client->exists( $this->fullKey( $key ) );
	}

	/**
	 * Deletes all items in the pool.
	 *
	 * @return bool
	 *   True if the pool was successfully cleared. False if there was an error.
	 */
	public function clear() {
		foreach ( $this->client->keys( $this->prefix . '*' ) as $fullKey ) {
			$this->client->del( $fullKey );
		}
		return true;
	}

	/**
	 * Removes the item from the pool.
	 *
	 * @param string $key
	 *   The key to delete.
	 *
	 * @throws InvalidArgumentException
	 *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
	 *   MUST be thrown.
	 *
	 * @return bool
	 *   True if the item was successfully removed. False if there was an error.
	 */
	public function deleteItem( $key ) {
		$this->client->del( $this->fullKey( $key ) );
		return true;
	}

	/**
	 * Removes multiple items from the pool.
	 *
	 * @param string[] $keys
	 *   An array of keys that should be removed from the pool.
	 * @throws InvalidArgumentException
	 *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
	 *   MUST be thrown.
	 *
	 * @return bool
	 *   True if the items were successfully removed. False if there was an error.
	 */
	public function deleteItems( array $keys ) {
		array_walk( $keys, [ $this, 'deleteItem' ] );
		return true;
	}

	/**
	 * Persists a cache item immediately.
	 *
	 * @param CacheItemInterface $item
	 *   The cache item to save.
	 *
	 * @return bool
	 *   True if the item was successfully persisted. False if there was an error.
	 */
	public function save( CacheItemInterface $item ) {
		if ( !$item instanceof SimpleCacheItem ) {
			throw new \InvalidArgumentException(
				'Cache items are not transferable between pools. I only work with items of type SimpleCacheItem.'
			);
		}
		$value = $item->get();
		if ( is_object( $value ) ) {
			throw new RuntimeException( 'Attempting to cache an object that cannot be JSON encoded' );
		}
		$encoded = json_encode( $value );
		if ( $item->getTtl() ) {
			$this->client->setex( $this->fullKey( $item->getKey() ), $item->getTtl(), $encoded );
		} else {
			$this->client->set( $this->fullKey( $item->getKey() ), $encoded );
		}
		return true;
	}

	/**
	 * Sets a cache item to be persisted later.
	 *
	 * @param CacheItemInterface $item
	 *   The cache item to save.
	 *
	 * @return bool
	 *   False if the item could not be queued or if a commit was attempted and failed. True otherwise.
	 */
	public function saveDeferred( CacheItemInterface $item ) {
		$this->deferredQueue[] = $item;
		return true;
	}

	/**
	 * Persists any deferred cache items.
	 *
	 * @return bool
	 *   True if all not-yet-saved items were successfully saved or there were none. False otherwise.
	 */
	public function commit() {
		$success = true;
		while ( $deferredItem = array_shift( $this->deferredQueue ) ) {
			$success = $success && $this->save( $deferredItem );
		}
		return $success;
	}
}
