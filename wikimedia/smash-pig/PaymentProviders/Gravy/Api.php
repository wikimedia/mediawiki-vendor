<?php

namespace SmashPig\PaymentProviders\Gravy;

use Gr4vy\Gr4vyConfig;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\PaymentData\PaymentMethod;

class Api {

	private GravySDKWrapper $gravyApiClient;

	public function __construct() {
		$c = Context::get()->getProviderConfiguration();

		$privateKeyLocation = $c->val( 'privateKeyLocation' );
		$gravyId = $c->val( 'gravy-id' );
		$apiPrefix = $c->val( 'api-prefix' );
		$merchantAccountId = $c->val( 'merchantAccountId' );

		$this->gravyApiClient = new GravySDKWrapper(
			new Gr4vyConfig( $gravyId, $privateKeyLocation, true, $apiPrefix, $merchantAccountId )
		);
	}

	/**
	 * Creates a new payment session for an apple or card payment
	 * @param array $params
	 * validation_url, domain_name, display_name, initiative, initiative_context
	 * @param string $method
	 * 'apple' or 'card'
	 * @return array
	 * @link https://docs.gr4vy.com/reference/checkout-sessions/new-checkout-session#create-checkout-session Gr4vy Documentation to create a new checkout session
	 * @link https://docs.gr4vy.com/reference/digital-wallets/get-apple-pay-session Gr4vy Documentation to create a new apple pay session
	 */
	public function createPaymentSession( array $params = [], string $method = 'card' ): array {
		$tl = new TaggedLogger( 'RawData' );
		$uniqueID = $params['validation_url'] ?? null;
		if ( $method === PaymentMethod::APPLE ) {
			$tl->info( 'New Apple Pay Session request ' . json_encode( $params ) );
			return $this->gravyApiClient->newApplePaySession( $uniqueID, $params );
		} else {
			$tl->info( 'New Checkout Session request ' . json_encode( $params ) );
			return $this->gravyApiClient->newCheckoutSession( $uniqueID, $params );
		}
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
		return $this->gravyApiClient->authorizeNewTransaction(
			$params['external_identifier'], $params, $headers
		);
	}

	/**
	 * Uses the rest API to capture the payment using the transaction ID
	 * received from the createPayment request
	 *
	 * @param string $trxn_id
	 * gateway_txn_id, amount
	 * @param array $requestBody
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/capture-transaction Documentation to approve payment
	 */
	public function approvePayment( string $trxn_id, array $requestBody ): array {
		$tl = new TaggedLogger( 'RawData' );
		$tl->info( "Approve payment request params: {\"trxn_id\":" . $trxn_id . "} " . json_encode( $requestBody ) );
		return $this->gravyApiClient->captureTransaction( $trxn_id, $trxn_id, $requestBody );
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
		return $this->gravyApiClient->deletePaymentMethod( $payment_method_id, $payment_method_id );
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
		return $this->gravyApiClient->getTransaction( $txn_id, $txn_id );
	}

	/**
	 * Uses the rest API to cancel an authorized transaction on Gravy
	 * @param string $gatewayTxnId
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/void-transaction
	 */
	public function cancelTransaction( string $gatewayTxnId ): array {
		return $this->gravyApiClient->voidTransaction( $gatewayTxnId, $gatewayTxnId, [] );
	}

	/**
	 * Uses the rest API to get a refund
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/list-transaction-events Documentation to delete payment token
	 */
	public function getRefund( array $params ): array {
		$refund_id = $params['gateway_refund_id'];
		return $this->gravyApiClient->getRefund( $refund_id, $refund_id );
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

		return $this->gravyApiClient->refundTransaction( $gatewayTxnId, $gatewayTxnId, $requestBody );
	}

	/**
	 * Uses the rest API to get a report execution id
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/reports/get-report-execution Documentation to get report execution details
	 */
	public function getReportExecutionDetails( array $params ): array {
		$report_execution_id = $params['report_execution_id'];
		return $this->gravyApiClient->getReportExecution( $report_execution_id, $report_execution_id );
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
		return $this->gravyApiClient->generateReportDownloadUrl(
			$report_execution_id, $report_id, $report_execution_id
		);
	}

	/**
	 * Uses the rest API to fetch the payment service definition for specified method
	 * @param string $method
	 * @return array
	 * @link https://docs.gr4vy.com/reference/payment-service-definitions/get-payment-service-definition#parameter-payment-service-definition-id
	 */
	public function getPaymentServiceDefinition( string $method = '' ): array {
		return $this->gravyApiClient->getPaymentServiceDefinition( $method, $method );
	}
}
