<?php

namespace SmashPig\PaymentProviders\Stripe;

use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

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
		$req = new OutboundRequest( $url, $method );
		$req->setHeader( 'Authorization', 'Bearer ' . $this->secretKey );
		$req->setHeader( 'User-Agent', 'SmashPig Stripe report downloader' );
		if ( $method !== 'GET' && $body ) {
			$req->setHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			$req->setBody( $body );
		}

		$response = $req->execute();
		$statusCode = $response['status'];

		if ( $statusCode < 200 || $statusCode >= 300 ) {
			throw new \RuntimeException(
				sprintf( 'Stripe request to %s failed with HTTP %d: %s', $url, $statusCode, $response['body'] )
			);
		}

		return $response['body'];
	}

	private function buildUrl( string $path ): string {
		if ( preg_match( '#^https?://#', $path ) ) {
			return $path;
		}
		return rtrim( $this->apiBase, '/' ) . '/' . ltrim( $path, '/' );
	}
}
