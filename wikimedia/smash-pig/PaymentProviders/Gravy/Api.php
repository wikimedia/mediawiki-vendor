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
	 * Creates a new payment session for an apple or card payment
	 */
	public function createPaymentSession( $params = [], $method = "card" ) {
		$response = null;
		$tl = new TaggedLogger( 'RawData' );
		if ( $method == "apple" ) {
			$response = $this->gravyApiClient->newApplePaySession( $params );
			$tl->info( "New Apple Pay Session response " . json_encode( $response ) );
		} else {
			$response = $this->gravyApiClient->newCheckoutSession( $params );
			$tl->info( "New Checkout Session response " . json_encode( $response ) );
		}
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
		$tl = new TaggedLogger( 'RawData' );
		$tl->info( "Create payment request params: " . json_encode( $params ) );
		$response = $this->gravyApiClient->authorizeNewTransaction( $params );
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
		$tl->info( "Approve payment request params: {\"trxn_id\":" . $trxn_id . "} " . json_encode( $requestBody ) );
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

	/**
	 * Uses the rest API to get a refund
	 * @param array $params
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/list-transaction-events Documentation to delete payment token
	 */
	public function getRefund( array $params ): array {
		$refund_id = $params['gateway_refund_id'];
		$tl = new TaggedLogger( 'RawData' );
		$response = $this->gravyApiClient->getRefund( $refund_id );
		$response_string = json_encode( $response );
		$tl->info( "Transaction details for transaction with ID {$refund_id} $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to refund a transaction on Gravy
	 * @param array $params
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 * @link https://docs.gr4vy.com/reference/transactions/refund-transaction
	 */
	public function refundTransaction( array $params ): array {
		$tl = new TaggedLogger( 'RawData' );
		$gatewayTxnId = $params['gateway_txn_id'];
		$requestBody = $params['body'];

		$response = $this->gravyApiClient->refundTransaction( $gatewayTxnId, $requestBody );
		$response_string = json_encode( $response );
		$tl->info( "Refund transaction response for transaction with ID $gatewayTxnId $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to get a report execution id
	 * @param array $params
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/reports/get-report-execution Documentation to get report execution details
	 */
	public function getReportExecutionDetails( array $params ): array {
		$report_execution_id = $params['report_execution_id'];
		$tl = new TaggedLogger( 'RawData' );
		$response = $this->gravyApiClient->getReportExecution( $report_execution_id );
		$response_string = json_encode( $response );
		$tl->info( "Report execution details for execution with ID {$report_execution_id} $response_string" );
		return $response;
	}

	/**
	 * Uses the rest API to generate a report from url
	 * @param array $params
	 * @throws \SmashPig\Core\ApiException
	 * @return array
	 * @link https://docs.gr4vy.com/reference/reports/get-report-execution Documentation to get report execution details
	 */
	public function generateReportDownloadUrl( array $params ): array {
		$report_id = $params['report_id'];
		$report_execution_id = $params['report_execution_id'];
		$tl = new TaggedLogger( 'RawData' );
		$response = $this->gravyApiClient->generateReportDownloadUrl( $report_id, $report_execution_id );
		$response_string = json_encode( $response );
		$tl->info( "Report url for report with execution ID {$report_execution_id} $response_string" );
		return $response;
	}
}
