<?php

namespace SmashPig\PaymentProviders\Gravy;

use Gr4vy\Gr4vyConfig;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\TaggedLogger;

class Api {

	private Gr4vyConfig $gravyApiClient;

	public function __construct() {
		$c = Context::get()->getProviderConfiguration();

		$privateKeyLocation = $c->val( 'privateKeyLocation' );
		$gravyId = $c->val( 'gravy-id' );
		$apiPrefix = $c->val( 'api-prefix' );
		$merchantAccountId = $c->val( 'merchantAccountId' );

		$this->gravyApiClient = new Gr4vyConfig( $gravyId, $privateKeyLocation, true, $apiPrefix, $merchantAccountId );
	}

	/**
	 * Creates a new payment session for an apple or card payment
	 */
	public function createPaymentSession( $params = [], $method = 'card' ): array {
		$tl = new TaggedLogger( 'RawData' );
		if ( $method === PaymentMethod::APPLE_PAY ) {
			$response = $this->gravyApiClient->newApplePaySession( $params );
			$tl->info( 'New Apple Pay Session response ' . json_encode( $response ) );
		} else {
			$response = $this->gravyApiClient->newCheckoutSession( $params );
			$tl->info( 'New Checkout Session response ' . json_encode( $response ) );
		}

		return self::handleGravySDKResponse( $method, $response, 'Create payment session' );
	}

	/**
	 * Initializes the two step payment process
	 *
	 *
	 * @param array $params
	 * amount, currency, payment_method
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/new-transaction Gr4vy Documentation to create a new transaction
	 */
	public function createPayment( array $params ): array {
		$tl = new TaggedLogger( 'RawData' );
		$tl->info( 'Create payment request params: ' . json_encode( $params ) );
		// This one parameter needs to be mapped here, as it is sent as a header rather than as
		// a POST request parameter.
		if ( empty( $params['user_ip'] ) ) {
			$headers = [];
		} else {
			$headers = [ 'X-Forwarded-For: ' . $params['user_ip'] ];
			unset( $params['user_ip'] );
		}
		$response = $this->gravyApiClient->authorizeNewTransaction( $params, $headers );
		return self::handleGravySDKResponse( $params['external_identifier'], $response, 'Create payment auth' );
	}

	/**
	 * Uses the rest API to capture the payment using the transaction ID
	 * received from the createPayment request
	 *
	 * @param string $trxn_id
	 * @param array $params
	 * gateway_txn_id, amount
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/capture-transaction Documentation to approve payment
	 */
	public function approvePayment( string $trxn_id, array $requestBody ): array {
		$tl = new TaggedLogger( 'RawData' );
		$tl->info( "Approve payment request params: {\"trxn_id\":" . $trxn_id . "} " . json_encode( $requestBody ) );
		$response = $this->gravyApiClient->captureTransaction( $trxn_id, $requestBody );

		return self::handleGravySDKResponse( $trxn_id, $response, 'Approve payment' );
	}

	/**
	 * Uses the rest API to delete a stored payment token on Gravy
	 *
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/guides/api/resources/payment-methods/delete#delete-a-payment-method Documentation to delete payment token
	 */
	public function deletePaymentToken( array $params ): array {
		$payment_method_id = $params['payment_method_id'];
		$response = $this->gravyApiClient->deletePaymentMethod( $payment_method_id );

		return self::handleGravySDKResponse( $payment_method_id, $response, 'Delete payment token' );
	}

	/**
	 * Uses the rest API to get the transaction details from Gravy
	 *
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/list-transaction-events Documentation to delete payment token
	 */
	public function getTransaction( array $params ): array {
		$txn_id = $params['gateway_txn_id'];
		$response = $this->gravyApiClient->getTransaction( $txn_id );

		return self::handleGravySDKResponse( $txn_id, $response, 'Get transaction' );
	}

	/**
	 * Uses the rest API to cancel an authorized transaction on Gravy
	 * @param string $gatewayTxnId
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/void-transaction
	 */
	public function cancelTransaction( string $gatewayTxnId ): array {
		$response = $this->gravyApiClient->voidTransaction( $gatewayTxnId, [] );

		return self::handleGravySDKResponse( $gatewayTxnId, $response, 'Cancel transaction' );
	}

	/**
	 * Uses the rest API to get a refund
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/list-transaction-events Documentation to delete payment token
	 */
	public function getRefund( array $params ): array {
		$refund_id = $params['gateway_refund_id'];
		$response = $this->gravyApiClient->getRefund( $refund_id );

		return self::handleGravySDKResponse( $refund_id, $response, 'Get refund' );
	}

	/**
	 * Uses the rest API to refund a transaction on Gravy
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/refund-transaction
	 */
	public function refundTransaction( array $params ): array {
		$gatewayTxnId = $params['gateway_txn_id'];
		$requestBody = $params['body'];

		$response = $this->gravyApiClient->refundTransaction( $gatewayTxnId, $requestBody );

		return self::handleGravySDKResponse( $gatewayTxnId, $response, 'Refund transaction' );
	}

	/**
	 * Uses the rest API to get a report execution id
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/reports/get-report-execution Documentation to get report execution details
	 */
	public function getReportExecutionDetails( array $params ): array {
		$report_execution_id = $params['report_execution_id'];
		$response = $this->gravyApiClient->getReportExecution( $report_execution_id );

		return self::handleGravySDKResponse( $report_execution_id, $response, 'Get report execution details' );
	}

	/**
	 * Uses the rest API to generate a report from url
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/reports/get-report-execution Documentation to get report execution details
	 */
	public function generateReportDownloadUrl( array $params ): array {
		$report_id = $params['report_id'];
		$report_execution_id = $params['report_execution_id'];
		$response = $this->gravyApiClient->generateReportDownloadUrl( $report_id, $report_execution_id );

		return self::handleGravySDKResponse( $report_execution_id, $response, 'Generate report download url' );
	}

	/**
	 * Uses the rest API to fetch the payment service definition for specified method
	 * @param string $method
	 * @return array
	 * @link https://docs.gr4vy.com/reference/payment-service-definitions/get-payment-service-definition#parameter-payment-service-definition-id
	 */
	public function getPaymentServiceDefinition( string $method = '' ): array {
		$response = $this->gravyApiClient->getPaymentServiceDefinition( $method );

		return self::handleGravySDKResponse( $method, $response, $method );
	}

	/**
	 * Handle Gravy SDK error responses (null, string, or unexpected types)
	 *
	 * @param string $uniqueIdentifier
	 * @param array|string|null $response
	 * @param string $functionName
	 * @return array|string[]
	 */
	public static function handleGravySDKResponse( string $uniqueIdentifier, null|array|string $response, string $functionName ): array {
		$tl = new TaggedLogger( 'RawData' );

		// Handle Gravy SDK error responses (null, string, or unexpected types)
		if ( $response === null ) {
			$errorMessage = "No response received from {$functionName} with {$uniqueIdentifier}";
		} elseif ( is_string( $response ) ) {
			$errorMessage = $response; // cURL error string
		} elseif ( !is_array( $response ) ) {
			$errorMessage = "Unexpected response from {$functionName} with {$uniqueIdentifier}";
		}

		if ( isset( $errorMessage ) ) {
			// simulate a Gravy-style error for our error mapper
			return [ 'type' => 'error', 'message' => $errorMessage ];
		}

		// Handle successful array response
		$formattedResponse = json_encode( $response );
		$tl->info( "{$functionName}: {$uniqueIdentifier} {$formattedResponse}" );
		return $response;
	}
}
