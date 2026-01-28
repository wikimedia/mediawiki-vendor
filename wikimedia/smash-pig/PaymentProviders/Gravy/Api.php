<?php

namespace SmashPig\PaymentProviders\Gravy;

use Gr4vy\Gr4vyConfig;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\ApiOperation;
use SmashPig\Core\Logging\ApiOperationAttribute;
use SmashPig\Core\Logging\ApiTimingTrait;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\PaymentData\PaymentMethod;

class Api {
	use ApiTimingTrait;

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
	 * @param array $params
	 * validation_url, domain_name, display_name, initiative, initiative_context
	 * @param string $method
	 * 'apple' or 'card'
	 * @return array
	 * @link https://docs.gr4vy.com/reference/checkout-sessions/new-checkout-session#create-checkout-session Gr4vy Documentation to create a new checkout session
	 * @link https://docs.gr4vy.com/reference/digital-wallets/get-apple-pay-session Gr4vy Documentation to create a new apple pay session
	 */
	#[ApiOperationAttribute( ApiOperation::CREATE_SESSION )]
	public function createPaymentSession( array $params = [], string $method = 'card' ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params, $method ) {
			$tl = new TaggedLogger( 'RawData' );
			if ( $method === PaymentMethod::APPLE ) {
				$tl->info( 'New Apple Pay Session request ' . json_encode( $params ) );
				$response = $this->gravyApiClient->newApplePaySession( $params );
			} else {
				$tl->info( 'New Checkout Session request ' . json_encode( $params ) );
				$response = $this->gravyApiClient->newCheckoutSession( $params );
			}

			return self::handleGravySDKResponse( $params['validation_url'] ?? null, $response, 'Create ' . $method . ' Payment Session' );
		} );
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
	#[ApiOperationAttribute( ApiOperation::AUTHORIZE )]
	public function createPayment( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
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
			return self::handleGravySDKResponse( $params['external_identifier'], $response, 'Create Payment Auth' );
		} );
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
	#[ApiOperationAttribute( ApiOperation::CAPTURE )]
	public function approvePayment( string $trxn_id, array $requestBody ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $trxn_id, $requestBody ) {
			$tl = new TaggedLogger( 'RawData' );
			$tl->info( "Approve payment request params: {\"trxn_id\":" . $trxn_id . "} " . json_encode( $requestBody ) );
			$response = $this->gravyApiClient->captureTransaction( $trxn_id, $requestBody );

			return self::handleGravySDKResponse( $trxn_id, $response, 'Approve Payment' );
		} );
	}

	/**
	 * Uses the rest API to delete a stored payment token on Gravy
	 *
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/guides/api/resources/payment-methods/delete#delete-a-payment-method Documentation to delete payment token
	 */
	#[ApiOperationAttribute( ApiOperation::DELETE_TOKEN )]
	public function deletePaymentToken( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
			$payment_method_id = $params['payment_method_id'];
			$response = $this->gravyApiClient->deletePaymentMethod( $payment_method_id );

			return self::handleGravySDKResponse( $payment_method_id, $response, 'Delete Payment Token' );
		} );
	}

	/**
	 * Uses the rest API to get the transaction details from Gravy
	 *
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/list-transaction-events Documentation to delete payment token
	 */
	#[ApiOperationAttribute( ApiOperation::GET_PAYMENT_STATUS )]
	public function getTransaction( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
			$txn_id = $params['gateway_txn_id'];
			$response = $this->gravyApiClient->getTransaction( $txn_id );

			return self::handleGravySDKResponse( $txn_id, $response, 'Get Transaction' );
		} );
	}

	/**
	 * Uses the rest API to cancel an authorized transaction on Gravy
	 * @param string $gatewayTxnId
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/void-transaction
	 */
	#[ApiOperationAttribute( ApiOperation::CANCEL )]
	public function cancelTransaction( string $gatewayTxnId ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $gatewayTxnId ) {
			$response = $this->gravyApiClient->voidTransaction( $gatewayTxnId, [] );

			return self::handleGravySDKResponse( $gatewayTxnId, $response, 'Cancel Transaction' );
		} );
	}

	/**
	 * Uses the rest API to get a refund
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/list-transaction-events Documentation to delete payment token
	 */
	#[ApiOperationAttribute( ApiOperation::GET_REFUND )]
	public function getRefund( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
			$refund_id = $params['gateway_refund_id'];
			$response = $this->gravyApiClient->getRefund( $refund_id );

			return self::handleGravySDKResponse( $refund_id, $response, 'Get Refund' );
		} );
	}

	/**
	 * Uses the rest API to refund a transaction on Gravy
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/refund-transaction
	 */
	#[ApiOperationAttribute( ApiOperation::REFUND )]
	public function refundTransaction( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
			$gatewayTxnId = $params['gateway_txn_id'];
			$requestBody = $params['body'];

			$response = $this->gravyApiClient->refundTransaction( $gatewayTxnId, $requestBody );

			return self::handleGravySDKResponse( $gatewayTxnId, $response, 'Refund Transaction' );
		} );
	}

	/**
	 * Uses the rest API to get a report execution id
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/reports/get-report-execution Documentation to get report execution details
	 */
	#[ApiOperationAttribute( ApiOperation::GET_REPORT_EXECUTION )]
	public function getReportExecutionDetails( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
			$report_execution_id = $params['report_execution_id'];
			$response = $this->gravyApiClient->getReportExecution( $report_execution_id );

			return self::handleGravySDKResponse( $report_execution_id, $response, 'Get Report Execution Details' );
		} );
	}

	/**
	 * Uses the rest API to generate a report from url
	 * @param array $params
	 * @return array
	 * @link https://docs.gr4vy.com/reference/reports/get-report-execution Documentation to get report execution details
	 */
	#[ApiOperationAttribute( ApiOperation::GET_REPORT_DOWNLOAD_URL )]
	public function generateReportDownloadUrl( array $params ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $params ) {
			$report_id = $params['report_id'];
			$report_execution_id = $params['report_execution_id'];
			$response = $this->gravyApiClient->generateReportDownloadUrl( $report_id, $report_execution_id );

			return self::handleGravySDKResponse( $report_execution_id, $response, 'Generate Report Download URL' );
		} );
	}

	/**
	 * Uses the rest API to fetch the payment service definition for specified method
	 * @param string $method
	 * @return array
	 * @link https://docs.gr4vy.com/reference/payment-service-definitions/get-payment-service-definition#parameter-payment-service-definition-id
	 */
	#[ApiOperationAttribute( ApiOperation::GET_PAYMENT_SERVICE_DEFINITION )]
	public function getPaymentServiceDefinition( string $method = '' ): array {
		return $this->timedCall( __FUNCTION__, function () use ( $method ) {
			$response = $this->gravyApiClient->getPaymentServiceDefinition( $method );

			return self::handleGravySDKResponse( $method, $response, 'Get Payment Service Definition' );
		} );
	}

	/**
	 * Handle Gravy SDK error responses (null, string, or unexpected types)
	 *
	 * @param ?string $uniqueIdentifier
	 * @param array|string|null $response
	 * @param string $functionName
	 * @return array|string[]
	 */
	public static function handleGravySDKResponse( ?string $uniqueIdentifier, null|array|string $response, string $functionName ): array {
		$tl = new TaggedLogger( 'RawData' );
		$preMessage = "{$functionName} response: " . ( $uniqueIdentifier ? "($uniqueIdentifier) " : "" );
		// Handle Gravy SDK error responses (null, string, or unexpected types)
		if ( $response === null ) {
			$errorMessage = $preMessage . "No response";
		} elseif ( is_string( $response ) ) {
			$errorMessage = $preMessage . $response;
		} elseif ( !is_array( $response ) ) {
			$errorMessage = $preMessage . "Unexpected response";
		}

		if ( isset( $errorMessage ) ) {
			$tl->info( $errorMessage );
			// simulate a Gravy-style error for our error mapper
			return [
				'type' => 'error',
				'message' => $errorMessage
			];
		}

		// Handle successful array response
		$formattedResponse = json_encode( $response );
		$tl->info( $preMessage . $formattedResponse );
		return $response;
	}
}
