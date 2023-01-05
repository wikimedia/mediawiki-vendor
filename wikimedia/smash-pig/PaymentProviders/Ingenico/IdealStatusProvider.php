<?php

namespace SmashPig\PaymentProviders\Ingenico;

use Psr\Cache\CacheItemPoolInterface;
use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

/**
 * Uses an unofficial API to look up status of iDEAL banks
 * see https://availability.ideal.nl
 */
class IdealStatusProvider {

	/**
	 * @var array()
	 */
	protected $cacheParameters;

	/**
	 * @var CacheItemPoolInterface
	 */
	protected $cache;

	/**
	 * @var string
	 */
	protected $availabilityUrl;

	public function __construct( array $options = [] ) {
		$this->cacheParameters = $options['cache-parameters'];
		$this->availabilityUrl = $options['availability-url'];
		// FIXME: provide objects in constructor
		$config = Context::get()->getGlobalConfiguration();
		$this->cache = $config->object( 'cache' );
	}

	/**
	 * Look up bank status
	 * @return array Keys are bank codes, values are names
	 * @throws \Psr\Cache\InvalidArgumentException
	 */
	public function getBankStatus(): array {
		$cacheKey = $this->cacheParameters['key'];
		$cacheItem = $this->cache->getItem( $cacheKey );

		if ( !$cacheItem->isHit() ) {
			$banks = [];

			$url = $this->availabilityUrl;

			$request = new OutboundRequest( $url );
			$rawResponse = $request->execute();
			$response = json_decode( $rawResponse['body'], true );

			foreach ( $response['Issuers'] as $issuer ) {
				$banks[$issuer['BankId']] = [
					'name' => $issuer['BankName'],
					'availability' => $issuer['Percent']
				];
			}

			$duration = $this->cacheParameters['duration'];

			$cacheItem->set( [
				'value' => $banks,
				# TODO: determine timezone and parse this format: '22-3-2017, 23:40'
				'lastupdate' => $response['LastUpdate'],
				'expiration' => time() + $duration
			] );
			$cacheItem->expiresAfter( $duration );
			$this->cache->save( $cacheItem );
		}
		$cached = $cacheItem->get();
		return $cached['value'];
	}
}
