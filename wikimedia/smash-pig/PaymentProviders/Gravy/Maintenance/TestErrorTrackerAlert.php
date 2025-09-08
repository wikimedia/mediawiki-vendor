<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorChecker;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorHelper;

/**
 * Test ErrorHelper::raiseAlert functionality to verify email alerts are sent
 * Simulates ErrorChecker/ErrorTracker outcomes before calling ErrorHelper::raiseAlert
 */
class TestErrorTrackerAlert extends MaintenanceBase {

	public function __construct() {
		parent::__construct();

		$this->desiredOptions['config-node']['default'] = 'gravy';
		$this->addOption( 'count', 'Number of times error occurred', false, false );
		$this->addOption( 'threshold', 'Threshold for alert ', false, false );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute(): void {
		$count = (int)$this->getOption( 'count', 20 );
		$threshold = (int)$this->getOption( 'threshold', 15 );
		$timeWindow = 1800; // 30 mins

		Logger::info( "Testing ErrorHelper::raiseAlert with unexpected_state payment error, count: $count, threshold: $threshold" );

		// Create realistic sample response data using real world unexpected_state error
		$response = $this->getSampleErrorResponse();

		// Extract error code and type like ErrorChecker would do
		$errorChecker = new ErrorChecker();
		$errorDetails = $errorChecker->getResponseErrorDetails( $response );

		// Build trackable error like the system would do
		$trackableError = ErrorHelper::buildTrackableErrorFromResponse(
			$errorDetails['error_code'],
			$errorDetails['error_type'],
			$response
		);

		Logger::info( "Simulated error details:", $errorDetails );
		Logger::info( "Sample trackable error data:", [
			'error_code' => $trackableError['error_code'],
			'error_type' => $trackableError['error_type'],
			'sample_transaction_id' => $trackableError['sample_transaction_id'],
			'sample_data' => $trackableError['sample_data']
		] );

		// Simulate threshold being exceeded - call ErrorHelper::raiseAlert directly
		Logger::info( "Calling ErrorHelper::raiseAlert to trigger email alert..." );

		ErrorHelper::raiseAlert(
			$trackableError['error_code'],
			$count,
			$threshold,
			$timeWindow,
			$trackableError
		);

		Logger::info( "ErrorHelper::raiseAlert called successfully. Check logs/emails for alert notification." );
	}

	/**
	 * Create sample error response data using real world Google Pay payment failure
	 * Based on real production error response
	 */
	private function getSampleErrorResponse(): array {
		return [
			"type" => "transaction",
			"id" => "dfaacf74-5df9-479b-942f-5077cfz5c9aa",
			"reconciliation_id" => "6z3LfNg5fhPHNGWdS3eaew",
			"merchant_account_id" => "default",
			"currency" => "BRL",
			"amount" => 6240,
			"status" => "authorization_failed",
			"authorized_amount" => 0,
			"captured_amount" => 0,
			"refunded_amount" => 0,
			"settled_currency" => null,
			"settled_amount" => 0,
			"settled" => false,
			"country" => "BR",
			"external_identifier" => "234102715.1",
			"intent" => "authorize",
			"payment_method" => [
				"type" => "payment-method",
				"approval_url" => null,
				"country" => "BR",
				"currency" => null,
				"details" => [
					"bin" => "12345",
					"card_type" => "prepaid",
					"card_issuer_name" => "Pagseguro Internet SA"
				],
				"expiration_date" => "03/27",
				"fingerprint" => "d7a98374112b53c42928ee13d7c179b24e958e4cb495e77c788899f15ba74ede",
				"label" => "6029",
				"last_replaced_at" => null,
				"method" => "googlepay",
				"mode" => "googlepay",
				"scheme" => "visa",
				"id" => null,
				"approval_target" => null,
				"external_identifier" => null,
				"payment_account_reference" => null
			],
			"method" => "googlepay",
			"instrument_type" => "pan",
			"error_code" => "unexpected_state",
			"payment_service" => [
				"type" => "payment-service",
				"id" => "725322ad-198d-484d-b66c-e6ff44712108",
				"payment_service_definition_id" => "adyen-card",
				"method" => "googlepay",
				"display_name" => "Adyen"
			],
			"pending_review" => false,
			"buyer" => [
				"type" => "buyer",
				"id" => null,
				"display_name" => null,
				"external_identifier" => "test@gmail.com",
				"billing_details" => [
					"first_name" => "Test",
					"last_name" => "McTesty",
					"email_address" => "test@gmail.com",
					"phone_number" => null,
					"address" => [
						"city" => "Betim",
						"country" => "BR",
						"postal_code" => "12345-350",
						"state" => "MG",
						"state_code" => null,
						"house_number_or_name" => null,
						"line1" => "12345 Brazil Street",
						"line2" => null,
						"organization" => null
					],
					"tax_id" => null
				],
				"account_number" => null
			],
			"raw_response_code" => null,
			"raw_response_description" => null,
			"shipping_details" => null,
			"checkout_session_id" => null,
			"gift_card_redemptions" => [],
			"gift_card_service" => null,
			"created_at" => "2025-08-16T23:31:34.754684+00:00",
			"updated_at" => "2025-08-16T23:32:08.006368+00:00",
			"airline" => null,
			"auth_response_code" => null,
			"avs_response_code" => null,
			"cvv_response_code" => null,
			"anti_fraud_decision" => null,
			"payment_source" => "ecommerce",
			"merchant_initiated" => false,
			"is_subsequent_payment" => false,
			"cart_items" => [],
			"statement_descriptor" => [
				"name" => null,
				"description" => "Wikimedia Foundation",
				"city" => null,
				"country" => null,
				"phone_number" => null,
				"url" => null
			],
			"scheme_transaction_id" => null,
			"three_d_secure" => null,
			"payment_service_transaction_id" => "C2ZXXG39DVKC42B9",
			"additional_identifiers" => [
				"payment_service_authorization_id" => null,
				"payment_service_capture_id" => null,
				"payment_service_processor_id" => null
			],
			"metadata" => null,
			"authorized_at" => null,
			"captured_at" => null,
			"voided_at" => null,
			"approval_expires_at" => "2025-08-17T00:01:34.806337+00:00",
			"buyer_approval_timedout_at" => null,
			"intent_outcome" => "failed",
			"multi_tender" => false,
			"account_funding_transaction" => false,
			"recipient" => null,
			"merchant_advice_code" => null,
			"installment_count" => null
		];
	}
}

$maintClass = TestErrorTrackerAlert::class;

require RUN_MAINTENANCE_IF_MAIN;
