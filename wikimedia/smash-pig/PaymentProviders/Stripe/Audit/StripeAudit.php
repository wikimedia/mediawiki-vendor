<?php

namespace SmashPig\PaymentProviders\Stripe\Audit;

use SmashPig\Core\DataFiles\AuditParser;

// Public Stripe audit parser entrypoint.
//
// This mirrors the PayPal approach: callers instantiate a single audit class,
// and this class selects the appropriate internal parser for the file.
//
// Detection rules:
// - Primary: filename prefix, because SmashPig controls the generated names.
// - Secondary: CSV header inspection, as a fallback for unexpected filenames.
class StripeAudit implements AuditParser {

	private string $sourceFilePath;

	public function parseFile( string $path ): array {
		$this->sourceFilePath = $path;
		$handle = fopen( $path, 'r' );
		if ( !$handle ) {
			throw new \RuntimeException( 'Unable to open file ' . $path );
		}

		$headers = fgetcsv( $handle );
		if ( !$headers ) {
			fclose( $handle );
			return [];
		}

		$output = [];
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( count( $row ) === 1 && trim( (string)$row[0] ) === '' ) {
				continue;
			}
			$parser = $this->getParser( $this->combineRow( $headers, $row ) );
			$output[] = $parser->normalizeRow();
		}

		fclose( $handle );
		return $output;
	}

	private function combineRow( array $headers, array $row ): array {
		$result = [];
		foreach ( $headers as $index => $header ) {
			$result[$header] = $row[$index] ?? '';
		}
		return $result;
	}

	private function getParser( array $row ): BaseParser {
		$filename = strtolower( basename( $this->sourceFilePath ) );

		if ( str_starts_with( $filename, 'settlement-' ) ) {
			return new SettlementParser( $row );
		}

		if ( str_starts_with( $filename, 'payments-' ) ) {
			return new PaymentsParser( $row );
		}

		$headers = $this->readHeaders();
		if ( $this->looksLikeSettlementFile( $headers ) ) {
			return new SettlementParser( $row );
		}

		if ( $this->looksLikePaymentsFile( $headers ) ) {
			return new PaymentsParser( $row );
		}

		throw new \RuntimeException( 'Unable to determine Stripe audit parser for file ' . $this->sourceFilePath );
	}

	private function readHeaders(): array {
		$handle = fopen( $this->sourceFilePath, 'r' );
		if ( !$handle ) {
			throw new \RuntimeException( 'Unable to open file ' . $this->sourceFilePath );
		}

		$headers = fgetcsv( $handle ) ?: [];
		fclose( $handle );
		return $headers;
	}

	private function looksLikeSettlementFile( array $headers ): bool {
		$headerMap = array_fill_keys( $headers, true );
		return isset( $headerMap['reporting_category'] ) ||
			isset( $headerMap['automatic_payout_effective_at'] ) ||
			isset( $headerMap['automatic_payout_effective_at_utc'] ) ||
			isset( $headerMap['settled_batch_reference'] );
	}

	private function looksLikePaymentsFile( array $headers ): bool {
		$headerMap = array_fill_keys( $headers, true );
		return isset( $headerMap['effective_at'] ) ||
			isset( $headerMap['effective_at_utc'] ) ||
			isset( $headerMap['activity_start_time'] ) ||
			isset( $headerMap['activity_end_time'] );
	}
}
