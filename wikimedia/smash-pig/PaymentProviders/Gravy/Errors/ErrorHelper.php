<?php

namespace SmashPig\PaymentProviders\Gravy\Errors;

use SmashPig\Core\Context;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\MailHandler;
use SmashPig\PaymentProviders\Gravy\GravyHelper;

class ErrorHelper {

	/**
	 * Builds a trackable error array by adding some extra info to the raw response
	 *
	 * @param string $errorCode The raw error code from Gravy.
	 * @param string $errorType The type of the error, describing its category.
	 * @param array $response The original response data to be augmented.
	 * @return array An enhanced response including the original data with normalized error info and a trxn summary.
	 */
	public static function buildTrackableError( string $errorCode, string $errorType, array $response ): array {
		$trackableError = $response;
		$trackableError['error_code'] = $errorCode;
		$trackableError['error_type'] = $errorType;
		$trackableError['sample_transaction_id'] = $response['id'] ?? $response['external_identifier'] ?? null;
		$trackableError['sample_transaction_summary'] = static::getTransactionSummaryFromResponse( $response );
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
		$sampleSummary = $errorContext['sample_transaction_summary'] ?? null;
		$timeWindowDisplay = static::formatTimeWindow( $timeWindow );
		$summaryString = $sampleSummary ? self::formatSummaryAsString( $sampleSummary ) : '';

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
			( $summaryString ? " - {$summaryString}" : '' ),
			$alertData
		);
	}

	protected static function formatSummaryAsString( array $summary ): string {
		$parts = [];

		if ( !empty( $summary['backend_processor'] ) ) {
			$parts[] = $summary['backend_processor'];
		}

		// external identifier aka ct_id
		if ( !empty( $summary['external_identifier'] ) ) {
			$parts[] = $summary['external_identifier'];
		}

		// Amount and currency
		if ( !empty( $summary['amount'] ) ) {
			$parts[] = $summary['amount'];
		}

		// Payment method
		if ( !empty( $summary['method'] ) ) {
			$parts[] = "via {$summary['method']}";
		}

		// Bin Info
		if ( !empty( $summary['bin'] ) ) {
			$parts[] = "BIN {$summary['bin']}";
		}

		// Country
		if ( !empty( $summary['country'] ) ) {
			$parts[] = "from {$summary['country']}";
		}

		return implode( ', ', $parts );
	}

	/**
	 * Build sample data array for logging
	 *
	 * @param array $response
	 * @return array
	 */
	protected static function getTransactionSummaryFromResponse( array $response ): array {
		$parts = [];

		// Backend processor
		$backendProcessor = static::extractBackendProcessor( $response );
		if ( $backendProcessor !== null ) {
			$parts['backend_processor'] = ucfirst( $backendProcessor );
		}

		// external identifier aka ct_id
		if ( isset( $response['external_identifier'] ) ) {
			$parts['external_identifier'] = $response['external_identifier'];
		}

		// Amount and currency
		if ( isset( $response['currency'] ) && isset( $response['amount'] ) ) {
			$formattedAmount = CurrencyRoundingHelper::getAmountInMajorUnits( $response['amount'], $response['currency'] );
			$parts['amount'] = "{$response['currency']} {$formattedAmount}";
		}

		// Payment method
		$method = $response['payment_method']['method'] ?? $response['method'] ?? null;
		if ( $method ) {
			$parts['method'] = $method;
		}

		// Bin Info
		$binNumber = $response['payment_method']['details']['bin'] ?? null;
		if ( $binNumber ) {
			$parts['bin'] = $binNumber;
		}

		// Country
		$country = $response['country'] ?? $response['buyer']['billing_details']['address']['country'] ?? null;
		if ( $country ) {
			$parts['country'] = $country;
		}

		return $parts;
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
	 * @param array $fraudTransactions Array of transactions with ids and summaries
	 * @return bool Success/failure of email sending
	 */
	public static function sendFraudTransactionsEmail( array $fraudTransactions ): bool {
		if ( empty( $fraudTransactions ) ) {
			return false;
		}

		$config = Context::get()->getProviderConfiguration();
		$to = $config->val( 'notifications/fraud-alerts/to' );
		$from = $config->val( 'email/from-address' );
		$subject = 'ALERT: Gravy Suspected Fraud Transactions List - ' . date( 'Y-m-d H:i' );
		$body = "Suspected fraud transactions (" . count( $fraudTransactions ) . ")" . PHP_EOL . PHP_EOL;
		foreach ( $fraudTransactions as $trxn ) {
			$body .= "https://wikimedia.gr4vy.app/merchants/default/transactions/{$trxn['id']}/overview " .
				self::formatSummaryAsString( $trxn['summary'] ) . PHP_EOL;
		}

		return MailHandler::sendEmail( $to, $subject, $body, $from );
	}
}
