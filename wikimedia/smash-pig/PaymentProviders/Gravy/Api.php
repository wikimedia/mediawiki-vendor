<?php

namespace SmashPig\PaymentProviders\Gravy;

use Gr4vy\Gr4vyConfig;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\TaggedLogger;

class Api {

	private $merchantAccountId;

	private $gravyApiClient;

	public function __construct() {
		$c = Context::get()->getProviderConfiguration();

		$privateKeyLocation = $c->val( 'privateKeyLocation' );
		$gravyId = $c->val( 'gravy-id' );
		$apiPrefix = $c->val( 'api-prefix' );

		$this->merchantAccountId = $c->val( 'merchantAccountId' );
		$this->gravyApiClient = new Gr4vyConfig( $gravyId, $privateKeyLocation, true, $apiPrefix, $this->merchantAccountId );
	}

	/**
	 * Creates a new checkout session
	 */
	public function createPaymentSession( $params = [] ) {
		$response = $this->gravyApiClient->newCheckoutSession( $params );
		$tl = new TaggedLogger( 'RawData' );
		$response_string = json_encode( $response );
		$tl->info( "New Checkout Session response $response_string" );
		return $response;
	}

	/**
	 * Get donor record to map transactions to on Gr4vy
	 *
	 *
	 * @param array $params
	 *
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/buyers/list-buyers Gr4vy Documentation to get an existing buyer
	 */
	public function getDonor( array $params ): array {
		$response = $this->gravyApiClient->listBuyers( $params );
		$tl = new TaggedLogger( 'RawData' );
		$response_string = json_encode( $response );
		$tl->info( "Get donor response $response_string" );
		return $response;
	}

	/**
	 * Create donor record to map transactions to on Gr4vy
	 *
	 *
	 * @param array $params
	 *
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/buyers/new-buyer Gr4vy Documentation to create a new buyer
	 */
	public function createDonor( array $params ): array {
		$response = $this->gravyApiClient->addBuyer( $params );
		$tl = new TaggedLogger( 'RawData' );
		$response_string = json_encode( $response );
		$tl->info( "Create donor response $response_string" );
		return $response;
	}

	/**
	 * Initializes the two step payment process
	 *
	 *
	 * @param array $params
	 * amount, currency, payment_method
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/new-transaction Gr4vy Documentation to create a new transaction
	 */
	public function createPayment( array $params ): array {
		$response = $this->gravyApiClient->authorizeNewTransaction( $params );
		$tl = new TaggedLogger( 'RawData' );
		$response_string = json_encode( $response );
		$tl->info( "Create payment response $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to capture the payment using the transaction ID
	 * received from the createPayment request
	 *
	 * @param string $trxn_id
	 * @param array $params
	 * gateway_txn_id, amount
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/capture-transaction Documentation to approve payment
	 */
	public function approvePayment( string $trxn_id, array $requestBody ): array {
		$tl = new TaggedLogger( 'RawData' );
		$response = $this->gravyApiClient->captureTransaction( $trxn_id, $requestBody );
		$response_string = json_encode( $response );
		$tl->info( "Approve payment response for Transaction ID {$trxn_id} $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to delete a stored payment token on Gravy
	 *
	 * @param array $params
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/guides/api/resources/payment-methods/delete#delete-a-payment-method Documentation to delete payment token
	 */
	public function deletePaymentToken( array $params ): array {
		$payment_method_id = $params['payment_method_id'];
		$tl = new TaggedLogger( 'RawData' );
		$response = $this->gravyApiClient->deletePaymentMethod( $payment_method_id );
		$response_string = json_encode( $response );
		$tl->info( "Delete payment token response for token {$payment_method_id} $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to get the transaction details from Gravy
	 *
	 * @param array $params
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/list-transaction-events Documentation to delete payment token
	 */
	public function getTransaction( array $params ): array {
		$txn_id = $params['gateway_txn_id'];
		$tl = new TaggedLogger( 'RawData' );
		$response = $this->gravyApiClient->getTransaction( $txn_id );
		$response_string = json_encode( $response );
		$tl->info( "Transaction details for transaction with ID {$txn_id} $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to cancel an authorized transaction on Gravy
	 * @param string $gatewayTxnId
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 * @link https://docs.gr4vy.com/reference/transactions/void-transaction
	 */
	public function cancelTransaction( string $gatewayTxnId ): array {
		$tl = new TaggedLogger( 'RawData' );
		$response = $this->gravyApiClient->voidTransaction( $gatewayTxnId, [] );
		$response_string = json_encode( $response );
		$tl->info( "Cancel transaction response for transaction with ID $gatewayTxnId $response_string" );
		return $response;
	}
}
