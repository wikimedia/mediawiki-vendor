<?php

namespace SmashPig\PaymentProviders\Gravy\Errors;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\MailHandler;
use SmashPig\PaymentProviders\Gravy\GravyHelper;

class ErrorHelper {

	/**
	 * Builds a trackable error object by enhancing the provided response with additional error details
	 *
	 * @param string $errorCode The raw error code from Gravy.
	 * @param string $errorType The type of the error, describing its category.
	 * @param array $response The original response data to be augmented.
	 * @return array An enhanced response array including the original data and additional error-related fields.
	 */
	public static function buildTrackableErrorFromResponse( string $errorCode, string $errorType, array $response ): array {
		// Create a trackable error using the API response
		$trackableError = array_merge( $response, [
			'error_code' => $errorCode,
			'error_type' => $errorType,
			'backend_processor' => static::extractBackendProcessor( $response )
		] );

		// Add latest sample data to $trackableError for alerting if needed
		$trackableError['sample_transaction_id'] = $response['id'] ?? $response['external_identifier'] ?? null;
		$trackableError['sample_data'] = static::buildSampleData( $response );

		return $trackableError;
	}

	/**
	 * Extract backend processor from response for context
	 *
	 * @param array $response
	 * @return string|null
	 */
	protected static function extractBackendProcessor( array $response ): ?string {
		if ( !empty( $response['payment_service']['payment_service_definition_id'] ) ) {
			$data['payment_service_definition_id'] = $response['payment_service']['payment_service_definition_id'];
			return GravyHelper::extractProcessorNameFromServiceDefinitionId( $data['payment_service_definition_id'] );
		}
		return null;
	}

	/**
	 * Raise alert when threshold is exceeded
	 *
	 * @param string $errorCode
	 * @param int $currentCount
	 * @param int $threshold
	 * @param int $timeWindow
	 * @param array $errorContext
	 */
	public static function raiseAlert( string $errorCode, int $currentCount, int $threshold, int $timeWindow, array $errorContext ): void {
		$sampleTransactionId = $errorContext['sample_transaction_id'] ?? null;
		$sampleContext = $errorContext['sample_data'] ?? '';
		$timeWindowDisplay = static::formatTimeWindow( $timeWindow );

		$alertData = [
			'error_code' => $errorCode,
			'current_count' => $currentCount,
			'threshold' => $threshold,
			'time_window_minutes' => $timeWindow / 60,
			'sample_transaction_id' => $sampleTransactionId,
		];

		Logger::alert(
			"ALERT: Gravy error threshold exceeded for error code '{$errorCode}'. " .
			"Occurred {$currentCount} times in {$timeWindowDisplay} (threshold: {$threshold})" .
			( $sampleTransactionId ? " - Sample transaction: {$sampleTransactionId}" : '' ) .
			$sampleContext,
			$alertData
		);
	}

	/**
	 * Build sample data string for logging
	 *
	 * @param array $response
	 * @return string
	 */
	protected static function buildSampleData( array $response ): string {
		$parts = [];

		// Amount and currency
		if ( isset( $response['amount'] ) && isset( $response['currency'] ) ) {
			$parts[] = "{$response['amount']} {$response['currency']}";
		}

		// Payment method
		$method = $response['payment_method']['method'] ?? $response['method'] ?? null;
		if ( $method ) {
			$parts[] = "via {$method}";
		}

		// Country
		$country = $response['country'] ?? $response['buyer']['billing_details']['address']['country'] ?? null;
		if ( $country ) {
			$parts[] = "from {$country}";
		}

		return $parts ? ' - ' . implode( ', ', $parts ) : '';
	}

	/**
	 * Format time window to show minutes when it makes sense
	 *
	 * @param int $timeWindowSeconds
	 * @return string
	 */
	protected static function formatTimeWindow( int $timeWindowSeconds ): string {
		if ( $timeWindowSeconds >= 60 && $timeWindowSeconds % 60 === 0 ) {
			$minutes = $timeWindowSeconds / 60;
			return $minutes === 1 ? "1 minute" : "{$minutes} minutes";
		}

		return $timeWindowSeconds === 1 ? "1 second" : "{$timeWindowSeconds} seconds";
	}

	/**
	 * Send basic fraud email with transaction IDs
	 *
	 * @param array $fraudTransactionIds Array of transaction ID strings
	 * @return bool Success/failure of email sending
	 */
	public static function sendFraudTransactionsEmail( array $fraudTransactionIds ): bool {
		if ( empty( $fraudTransactionIds ) ) {
			return false;
		}

		$config = Context::get()->getProviderConfiguration();
		$to = $config->val( 'notifications/fraud-alerts/to' );
		$from = $config->val( 'email/from-address' );
		$subject = 'ALERT: Gravy Suspected Fraud Transactions List - ' . date( 'Y-m-d H:i' );
		$body = "Suspected fraud transactions (" . count( $fraudTransactionIds ) . ")" . PHP_EOL . PHP_EOL;
		foreach ( $fraudTransactionIds as $trxn ) {
			$body .= " - https://wikimedia.gr4vy.app/merchants/default/transactions/{$trxn['id']}/overview" . $trxn['info'] . PHP_EOL;
		}

		return MailHandler::sendEmail( $to, $subject, $body, $from );
	}
}
