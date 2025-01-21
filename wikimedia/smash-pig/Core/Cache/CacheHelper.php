<?php

namespace SmashPig\Core\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use SmashPig\Core\Context;

class CacheHelper {

	/**
	 * Gets a value from the cache, or if not present, from a callback. When retrieved
	 * from the callback, the new value will be added to the cache. Inspired by
	 * https://phabricator.wikimedia.org/source/mediawiki/browse/master/includes/libs/objectcache/BagOStuff.php$204
	 *
	 * @param string $key Cache key to check and / or cache the item
	 * @param int $duration Number of seconds to cache the item
	 * @param callable $callback Function to retrieve a fresh value for the item
	 * @return mixed
	 * @throws \Psr\Cache\InvalidArgumentException
	 * @throws \ReflectionException
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public static function getWithSetCallback( string $key, int $duration, callable $callback ) {
		/** @var CacheItemPoolInterface $cache */
		$cache = Context::get()->getGlobalConfiguration()->object( 'cache' );
		$cacheItem = $cache->getItem( $key );
		if ( !$cacheItem->isHit() || self::shouldBeExpired( $cacheItem ) ) {
			$value = $callback();
			$cacheItem->set( [
				'value' => $value,
				'expiration' => time() + $duration
			] );
			$cacheItem->expiresAfter( $duration );
			$cache->save( $cacheItem );
		}
		$cached = $cacheItem->get();
		return $cached['value'];
	}

	/**
	 * Lame workaround to mysterious Memcache non-expiry bug. Memcache
	 * seems to hold things for too long in certain circumstances.
	 *
	 * @param CacheItemInterface $cacheItem
	 * @return bool True if the item should have been dropped by Memcache
	 */
	protected static function shouldBeExpired( CacheItemInterface $cacheItem ): bool {
		$value = $cacheItem->get();
		if ( !isset( $value['expiration'] ) ) {
			return true;
		}
		$expiration = $value['expiration'];
		return $expiration < time();
	}
}
