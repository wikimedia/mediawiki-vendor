<?php

namespace SmashPig\PaymentProviders\Overflow\Audit;

use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

class Api {

	private const DEFAULT_API_BASE = 'https://server.overflow.co/api/v3';

	private string $clientId;
	private string $apiKey;
	private string $apiBase;

	/**
	 * @param array $params
	 *   Expects a 'gateway_account' key (e.g. 'Foundation', 'Endowment'),
	 *   used to look up that account's credentials under the config's
	 *   'accounts' node.
	 */
	public function __construct( array $params ) {
		$config = Context::get()->getProviderConfiguration();

		$accounts = $config->val( 'accounts' );
		$account = $accounts[$params['gateway_account']];

		$this->clientId = $account['client_id'];
		$this->apiKey = $account['api_key'];
		$this->apiBase = $config->has( 'api_base_url' ) ? $config->val( 'api_base_url' ) : self::DEFAULT_API_BASE;
	}

	/**
	 * Get a page of deposits.
	 *
	 * @param array $query
	 *
	 * @return array
	 */
	public function getDeposits( array $query = [] ): array {
		return $this->requestJson( 'GET', '/deposits', $query );
	}

	/**
	 * Get a single deposit.
	 *
	 * @param string $depositId
	 *
	 * @return array
	 */
	public function getDeposit( string $depositId ): array {
		return $this->requestJson(
			'GET',
			'/deposits/' . rawurlencode( $depositId )
		);
	}

	/**
	 * Get a deposit summary.
	 *
	 * @param string $depositId
	 *
	 * @return array
	 */
	public function getDepositSummary( string $depositId ): array {
		return $this->requestJson(
			'GET',
			'/deposits/' . rawurlencode( $depositId ) . '/summary'
		);
	}

	/**
	 * Get a single contribution.
	 *
	 * @param string $contributionId
	 *
	 * @return array
	 */
	public function getContribution( string $contributionId ): array {
		return $this->requestJson(
			'GET',
			'/contributions/' . rawurlencode( $contributionId )
		);
	}

	/**
	 * Get a deposit and all contributions referenced by its line items.
	 *
	 * The returned structure deliberately retains both the line item and
	 * contribution so that the eventual parser can determine how the
	 * Overflow data maps to SmashPig audit rows.
	 *
	 * @param string $depositId
	 *
	 * @return array
	 */
	public function getDepositWithContributions( string $depositId ): array {
		$deposit = $this->getDeposit( $depositId )['data'];

		$contributions = [];

		foreach ( $deposit['lineItems'] ?? [] as $lineItem ) {
			$referenceId = $lineItem['referenceId'] ?? '';

			if ( !str_starts_with( $referenceId, 'tr_ad_' ) ) {
				// Skipping this will probably cause it to fail validation later.
				// Where we have seen this is an odd manual transfer added in by an overflow
				// manual intervention - if these occur they will require analysis/intervention
				// of some kind.
				continue;
			}

			$contributionId = substr( $referenceId, strlen( 'tr_ad_' ) );

			$contributionResponse = $this->getContribution( $contributionId );

			$contribution = $contributionResponse['data'] ?? null;

			if ( !is_array( $contribution ) ) {
				throw new \RuntimeException(
					'Overflow contribution response did not contain data for contribution ' .
					$contributionId
				);
			}

			$contributions[] = [
				'lineItem' => $lineItem,
				'contribution' => $contribution,
			];
		}

		return [
			'deposit' => $deposit,
			'summary' => $this->getDepositSummary( $depositId ),
			'contributions' => $contributions,
		];
	}

	/**
	 * Make an authenticated Overflow API request.
	 *
	 * @param string $method
	 * @param string $path
	 * @param array $query
	 * @param array|null $body
	 *
	 * @return array
	 */
	public function requestJson(
		string $method,
		string $path,
		array $query = [],
		?array $body = null
	): array {
		$url = $this->buildUrl( $path, $query );

		$request = new OutboundRequest( $url, $method );
		$request->setHeader( 'x-client-id', $this->clientId );
		$request->setHeader( 'x-api-key', $this->apiKey );
		$request->setHeader( 'Accept', 'application/json' );

		if ( $body !== null ) {
			$request->setHeader( 'Content-Type', 'application/json' );
			$request->setBody(
				json_encode( $body, JSON_THROW_ON_ERROR )
			);
		}

		$response = $request->execute();
		$statusCode = $response['status'];

		if ( $statusCode < 200 || $statusCode >= 300 ) {
			throw new \RuntimeException(
				sprintf(
					'Overflow API request failed with HTTP %d: %s',
					$statusCode,
					$response['body'] ?? ''
				)
			);
		}

		$result = json_decode(
			$response['body'],
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		if ( !is_array( $result ) ) {
			throw new \RuntimeException(
				'Overflow API returned an unexpected response'
			);
		}

		return $result;
	}

	private function buildUrl( string $path, array $query = [] ): string {
		$url = rtrim( $this->apiBase, '/' ) . '/' . ltrim( $path, '/' );

		if ( $query !== [] ) {
			$url .= '?' . http_build_query( $query );
		}

		return $url;
	}
}
