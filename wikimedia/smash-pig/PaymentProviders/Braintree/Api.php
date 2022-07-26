<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\Http\OutboundRequest;

class Api {

	/**
	 * @var string
	 */
	protected $merchantId;

	/**
	 * @var string
	 */
	protected $privateKey;

	/**
	 * @var string
	 */
	protected $publicKey;

	/**
	 * @var string
	 */
	protected $endpoint;

	/**
	 * @var string
	 */
	protected $version;

	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		$this->merchantId = $params['merchant-id'];
		$this->privateKey = $params['private-key'];
		$this->publicKey = $params['public-key'];
		$this->endpoint = $params['endpoint'];
		$this->version = $params['version'];
	}

	/**
	 * @return array
	 */
	public function ping(): array {
		return $this->makeApiCall( 'query { ping }' );
	}

	/**
	 * @param string $date
	 * format "YYYY-MM-DD"
	 * Will only be useful for Venmo if we have a merchant account that is priced as IC+
	 * developer.paypal.com/braintree/articles/control-panel/reporting/transaction-level-fee-report
	 * @return array
	 */
	public function report( string $date ): array {
		$query = $this->getQuery( 'GetReport' );
		$variables = [ 'date' => $date ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 * @param array $input
	 * @param ?string $after
	 * @return array
	 */
	public function searchTransactions( array $input, ?string $after ): array {
		$query = $this->getQuery( 'SearchTransactions' );
		$variables = [ 'input' => $input, 'after' => $after ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 * @param array $input
	 * @param ?string $after
	 * @return array
	 */
	public function searchRefunds( array $input, ?string $after ): array {
		$query = $this->getQuery( 'SearchRefunds' );
		$variables = [ 'input' => $input, 'after' => $after ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 * @param array $input
	 * @param ?string $after
	 * @return array
	 */
	public function searchDisputes( array $input, ?string $after ): array {
		$query = $this->getQuery( 'SearchDisputes' );
		$variables = [ 'input' => $input, 'after' => $after ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 * @return array
	 */
	public function createClientToken(): array {
		$query = $this->getQuery( 'createClientToken' );
		return $this->makeApiCall( $query );
	}

	/**
	 *
	 * @param array $input
	 * @return array
	 */
	public function chargePayment( array $input ): array {
		$query = $this->getQuery( 'ChargePaymentMethod' );
		$variables = [ 'input' => $input ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 *
	 * @param array $input
	 * @return array
	 */
	public function authorizePayment( array $input ): array {
		$query = $this->getQuery( 'AuthorizePaymentMethod' );
		$variables = [ 'input' => $input ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 *
	 * @param array $input
	 * @return array
	 */
	public function captureTransaction( array $input ): array {
		$query = $this->getQuery( 'CaptureTransaction' );
		$variables = [ 'input' => $input ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 * Submit query/mutation GraphQL calls to Braintree
	 *
	 * @param string $query graphql query/mutation string
	 * @param array $variables graphql query/mutation variables
	 * @return array
	 */
	protected function makeApiCall( string $query, array $variables = [] ): array {
		$request = new OutboundRequest( $this->endpoint, 'POST' );
		$request->setHeader( 'Authorization', 'Basic ' . $this->getAuthorizationHeader() );
		$request->setHeader( 'Braintree-Version', $this->version );
		$request->setHeader( 'Content-Type', 'application/json' );
		$body = [ 'query' => $query ];
		if ( !empty( $variables ) ) {
			$body['variables'] = $variables;
		}
		$request->setBody( json_encode( $body ) );
		return json_decode( $request->execute()['body'], true );
	}

	/**
	 * create a Base64-encoded token
	 * which is braintree’s only key that can be used to charge money
	 * @return string
	 */
	protected function getAuthorizationHeader() {
		return base64_encode(
			$this->publicKey . ':' . $this->privateKey
		);
	}

	/**
	 * Read in Braintree graphQL query file.
	 *
	 * @param string $name
	 * @return string
	 */
	protected function getQuery( string $name ): string {
		$queryDir = __DIR__ . '/Queries/';
		$queryFileExt = '.graphql';
		$queryFilePath = $queryDir . $name . $queryFileExt;
		if ( file_exists( $queryFilePath ) ) {
			return file_get_contents( $queryFilePath );
		} else {
			throw new \UnexpectedValueException( "Unable to find query file '{$queryFilePath}'" );
		}
	}
}
