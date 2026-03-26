<?php

namespace SmashPig\PaymentProviders\Stripe;

use SmashPig\Core\Context;

class Api {

	private string $secretKey;

	private string $apiBase;

	public function __construct( array $params ) {
		$accounts = Context::get()->getProviderConfiguration()->val( 'accounts' );
		$this->secretKey = $accounts[$params['gateway_account']]['secret'];
		$this->apiBase = Context::get()->getProviderConfiguration()->val( 'api_base_url' );
	}

	/**
	 * Create a Stripe report run.
	 * Docs: https://docs.stripe.com/reports/api
	 */
	public function createReportRun( string $reportType, array $parameters ): array {
		$body = [
			'report_type' => $reportType,
		];

		foreach ( $parameters as $key => $value ) {
			if ( $value === null || $value === '' ) {
				continue;
			}
			$body["parameters[$key]"] = $value;
		}

		return $this->requestJson( 'POST', '/reporting/report_runs', $body );
	}

	/**
	 * Retrieve a Stripe report run until it reaches succeeded or failed.
	 * Docs: https://docs.stripe.com/reports/api
	 */
	public function getReportRun( string $reportRunId ): array {
		return $this->requestJson( 'GET', '/reporting/report_runs/' . rawurlencode( $reportRunId ) );
	}

	/**
	 * List payouts with optional status and date filters.
	 *
	 * // Docs: https://docs.stripe.com/api/payouts/list
	 *
	 * @param array $filters
	 *
	 * @return array
	 */
	public function listPayouts( array $filters = [] ): array {
		$query = [];
		foreach ( [ 'status', 'starting_after', 'ending_before', 'limit' ] as $simpleKey ) {
			if ( isset( $filters[$simpleKey] ) && $filters[$simpleKey] !== '' && $filters[$simpleKey] !== null ) {
				$query[$simpleKey] = $filters[$simpleKey];
			}
		}

		foreach ( [ 'arrival_date', 'created' ] as $rangeKey ) {
			if ( isset( $filters[$rangeKey] ) && is_array( $filters[$rangeKey] ) ) {
				foreach ( $filters[$rangeKey] as $key => $value ) {
					if ( $value !== null && $value !== '' ) {
						$query["{$rangeKey}[$key]"] = $value;
					}
				}
			}
		}

		$path = '/payouts';
		if ( $query ) {
			$path .= '?' . http_build_query( $query );
		}

		return $this->requestJson( 'GET', $path );
	}

	/**
	 * Retrieve a payout object.
	 * Docs: https://docs.stripe.com/api/payouts/object
	 *
	 * @param string $payoutId
	 *
	 * @return array
	 */
	public function getPayout( string $payoutId ): array {
		return $this->requestJson( 'GET', '/payouts/' . rawurlencode( $payoutId ) );
	}

	/**
	 * List balance transactions for a single payout.
	 *
	 * Docs: https://docs.stripe.com/api/balance_transactions/list
	 *
	 * @param string $payoutId
	 * @param string|null $startingAfter
	 *
	 * @return array
	 */
	public function listBalanceTransactionsForPayout( string $payoutId, ?string $startingAfter = null ): array {
		$query = [
			'payout' => $payoutId,
			'limit' => 100,
		];
		if ( $startingAfter ) {
			$query['starting_after'] = $startingAfter;
		}
		return $this->requestJson( 'GET', '/balance_transactions?' . http_build_query( $query ) );
	}

	/**
	 * Retrieve a charge and expand the related PaymentIntent.
	 * Docs: https://docs.stripe.com/api/charges
	 *
	 * @param string $chargeId
	 *
	 * @return array
	 */
	public function getCharge( string $chargeId ): array {
		return $this->requestJson( 'GET', '/charges/' . rawurlencode( $chargeId ) . '?' . http_build_query( [
			'expand' => [ 'payment_intent' ],
		] ) );
	}

	/**
	 * Retrieve a refund and expand the related charge and PaymentIntent.
	 * Docs: https://docs.stripe.com/api/refunds
	 */
	public function getRefund( string $refundId ): array {
		return $this->requestJson( 'GET', '/refunds/' . rawurlencode( $refundId ) . '?' . http_build_query( [
			'expand' => [ 'charge.payment_intent' ],
		] ) );
	}

	/**
	 * Retrieve a dispute and expand the related charge and PaymentIntent.
	 * Docs: https://docs.stripe.com/api/disputes
	 */
	public function getDispute( string $disputeId ): array {
		return $this->requestJson( 'GET', '/disputes/' . rawurlencode( $disputeId ) . '?' . http_build_query( [
			'expand' => [ 'charge.payment_intent' ],
		] ) );
	}

	public function downloadFile( string $downloadUrl ): string {
		return $this->requestRaw( 'GET', $downloadUrl );
	}

	private function requestJson( string $method, string $path, array $body = [] ): array {
		$response = $this->requestRaw( $method, $this->buildUrl( $path ), $body );
		$result = json_decode( $response, true );
		if ( !is_array( $result ) ) {
			throw new \RuntimeException( 'Stripe API returned invalid JSON' );
		}
		if ( isset( $result['error']['message'] ) ) {
			throw new \RuntimeException( 'Stripe API error: ' . $result['error']['message'] );
		}
		return $result;
	}

	private function requestRaw( string $method, string $url, array $body = [] ): string {
		$ch = curl_init();
		if ( $ch === false ) {
			throw new \RuntimeException( 'Unable to initialise curl' );
		}

		$headers = [
			'Authorization: Bearer ' . $this->secretKey,
			'User-Agent: SmashPig Stripe report downloader',
		];

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 300 );

		if ( $method !== 'GET' && $body ) {
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $body ) );
		}

		$response = curl_exec( $ch );
		$statusCode = (int)curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $ch );
		curl_close( $ch );

		if ( $response === false ) {
			throw new \RuntimeException( 'Stripe request failed: ' . $error );
		}

		if ( $statusCode < 200 || $statusCode >= 300 ) {
			throw new \RuntimeException( sprintf( 'Stripe request failed with HTTP %d: %s', $statusCode, $response ) );
		}

		return $response;
	}

	private function buildUrl( string $path ): string {
		if ( preg_match( '#^https?://#', $path ) ) {
			return $path;
		}
		return rtrim( $this->apiBase, '/' ) . '/' . ltrim( $path, '/' );
	}
}
