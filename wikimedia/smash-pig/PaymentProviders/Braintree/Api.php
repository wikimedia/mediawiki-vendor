<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\Http\OutboundRequest;
use SmashPig\Core\Logging\ApiOperation;
use SmashPig\Core\Logging\ApiOperationAttribute;
use SmashPig\Core\Logging\ApiTimingTrait;

class Api {
	use ApiTimingTrait;

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
	 * @param array $params
	 * https://graphql.braintreepayments.com/reference/#Mutation--refundTransaction
	 * @return array
	 */
	#[ApiOperationAttribute( ApiOperation::REFUND )]
	public function refundPayment( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
			$query = $this->getQuery( 'RefundPayment' );
			$input = [
				'transactionId' => $params['gateway_txn_id'],
				'refund' => [
					'orderId' => $params['order_id']
				]
			];
			// only if we want partial refund, or we can omit this para,
			// we can also add reason of refund, but probably not important
			if ( isset( $params['amount'] ) ) {
				$input['refund']['amount'] = $params['amount'];
			}
			return $this->makeApiCall( $query, [ 'input' => $input ] );
		} );
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
	#[ApiOperationAttribute( ApiOperation::CREATE_SESSION )]
	public function createClientToken(): array {
		return $this->timedCall( __FUNCTION__, function () {
			$query = $this->getQuery( 'createClientToken' );
			return $this->makeApiCall( $query );
		} );
	}

	/**
	 *
	 * @param array $input
	 * @return array
	 */
	#[ApiOperationAttribute( ApiOperation::CAPTURE )]
	public function captureTransaction( array $input ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $input ) {
			$query = $this->getQuery( 'CaptureTransaction' );
			$variables = [ 'input' => $input ];
			return $this->makeApiCall( $query, $variables );
		} );
	}

	/**
	 * fetch customer info if client side failed to return for auth
	 * @param string $id
	 * @return array
	 */
	public function fetchCustomer( string $id ): array {
		$query = $this->getQuery( 'FetchCustomer' );
		$variables = [ 'id' => $id ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 * search customer id to use it as key to delete from vault for one-time tokenized donor
	 * @param string $email
	 * @return array
	 */
	public function searchCustomer( string $email ): array {
		$query = $this->getQuery( 'SearchCustomer' );
		$variables = [ 'input' => [
			'email' => [
				'is' => $email
			]
		] ];
		return $this->makeApiCall( $query, $variables );
	}

	/**
	 * delete recurring token if turn on post MC but declined
	 * @param string $paymentTxnId
	 * @return array
	 */
	#[ApiOperationAttribute( ApiOperation::DELETE_TOKEN )]
	public function deletePaymentMethodFromVault( string $paymentTxnId ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $paymentTxnId ) {
			$query = $this->getQuery( 'DeletePaymentMethodFromVault' );
			$variables = [ "input" => [
				"clientMutationId" => $paymentTxnId,
				"paymentMethodId" => $paymentTxnId,
				"fraudRelated" => false,
				"deleteRelatedPaymentMethods" => false,
				"initiatedBy" => "MERCHANT"
			] ];
			return $this->makeApiCall( $query, $variables );
		} );
	}

	/**
	 * remove customer if turn on post MC but declined
	 * @param string $customerId
	 * @return array
	 */
	#[ApiOperationAttribute( ApiOperation::DELETE_DATA )]
	public function deleteCustomer( string $customerId ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $customerId ) {
			$query = $this->getQuery( 'DeleteCustomer' );
			$variables = [ 'input' => [
				'clientMutationId' => $customerId,
				'customerId' => $customerId
			] ];
			return $this->makeApiCall( $query, $variables );
		} );
	}

	/**
	 *
	 * @param array $input
	 * @return array
	 */
	#[ApiOperationAttribute( ApiOperation::AUTHORIZE )]
	public function authorizePaymentMethod( array $input ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $input ) {
			$query = $this->getQuery( 'AuthorizePaymentMethod' );
			$variables = [ 'input' => $input ];
			return $this->makeApiCall( $query, $variables );
		} );
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
