<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\ApiException;
use SmashPig\Core\Cache\CacheHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handle bank payments via Ingenico
 * Will eventually implement PaymentProvider, but right now just looks
 * up iDEAL banks. Caches the results in the PSR-6 cache defined at
 * config key 'cache'.
 */
class BankPaymentProvider extends PaymentProvider {

	/**
	 * @var array
	 */
	protected $cacheParameters;

	public function __construct( array $options = [] ) {
		parent::__construct( $options );
		$this->cacheParameters = $options['cache-parameters'];
	}

	/**
	 * Look up banks
	 * @param string $country 2 letter country ISO code
	 * @param string $currency 3 letter currency ISO code
	 * @param int $productId Numeric Ingenico id of payment product we're
	 *  listing banks for. Defaults to the code for iDEAL, the only product
	 *  supported as of early 2017
	 * @return array Keys are bank codes, values are names
	 * @throws ApiException
	 * @throws \Psr\Cache\InvalidArgumentException
	 */
	public function getBankList( string $country, string $currency, int $productId = 809 ): array {
		$cacheKey = $this->makeCacheKey( $country, $currency, $productId );
		$bankLookupCallback = function () use ( $country, $currency, $productId ) {
			$query = [
				'countryCode' => $country,
				'currencyCode' => $currency
			];
			$path = "products/$productId/directory";
			$banks = [];

			$response = $this->api->makeApiCall( $path, 'GET', $query );
			$this->checkForErrors( $response );

			if ( !empty( $response['entries'] ) ) {
				foreach ( $response['entries'] as $entry ) {
					$banks[$entry['issuerId']] = $entry['issuerName'];
				}
			}
			return $banks;
		};
		return CacheHelper::getWithSetCallback( $cacheKey, $this->cacheParameters['duration'], $bankLookupCallback );
	}

	protected function makeCacheKey( string $country, string $currency, int $productId ): string {
		$base = $this->cacheParameters['key-base'];
		return "{$base}_{$country}_{$currency}_{$productId}";
	}

	protected function checkForErrors( array $response ) {
		if ( empty( $response['errors'] ) ) {
			return;
		}
		$errors = $response['errors'];
		if ( count( $errors ) === 1 && $errors[0]['httpStatusCode'] === Response::HTTP_NOT_FOUND ) {
			// If there is a single 404 error, that means there is no directory info for the
			// country/currency/product. That's legitimate, so return and cache the empty array
			return;
		}
		$stringified = json_encode( $errors );
		$apiException = new ApiException(
			"Ingenico Error {$response['error_id']}: $stringified"
		);
		$apiException->setRawErrors( $errors );
		throw $apiException;
	}
}
