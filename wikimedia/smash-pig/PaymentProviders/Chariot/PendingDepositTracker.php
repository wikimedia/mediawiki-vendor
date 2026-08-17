<?php

namespace SmashPig\PaymentProviders\Chariot;

class PendingDepositTracker {

	private const FILE_PREFIX = 'pending-chariot-deposit-';

	public function __construct( private readonly string $directory ) {
	}

	public function markPending( string $depositId, string $reason ): void {
		$path = $this->getFilePath( $depositId );
		$now = gmdate( 'Y-m-d\TH:i:s\Z' );

		$payload = [];
		if ( is_file( $path ) ) {
			$existing = json_decode( (string)file_get_contents( $path ), true );
			if ( is_array( $existing ) ) {
				$payload = $existing;
			}
		}

		$payload['deposit_id'] = $depositId;
		$payload['first_seen'] = $payload['first_seen'] ?? $now;
		$payload['last_attempt'] = $now;
		$payload['attempts'] = (int)( $payload['attempts'] ?? 0 ) + 1;
		$payload['reason'] = $reason;

		$this->writeJsonFile( $path, $payload );
	}

	public function markResolved( string $depositId ): void {
		$path = $this->getFilePath( $depositId );
		if ( is_file( $path ) && !unlink( $path ) ) {
			throw new \RuntimeException( 'Unable to remove pending Chariot deposit file: ' . $path );
		}
	}

	/**
	 * @return string[]
	 */
	public function getPendingDepositIds(): array {
		$files = glob( $this->directory . '/' . self::FILE_PREFIX . '*.json' );
		if ( $files === false ) {
			return [];
		}

		$depositIds = [];
		foreach ( $files as $file ) {
			$payload = json_decode( (string)file_get_contents( $file ), true );
			if ( is_array( $payload ) && !empty( $payload['deposit_id'] ) ) {
				$depositIds[] = (string)$payload['deposit_id'];
			}
		}

		return array_values( array_unique( $depositIds ) );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function getPendingPayload( string $depositId ): ?array {
		$path = $this->getFilePath( $depositId );
		if ( !is_file( $path ) ) {
			return null;
		}

		$payload = json_decode( (string)file_get_contents( $path ), true );
		return is_array( $payload ) ? $payload : null;
	}

	private function getFilePath( string $depositId ): string {
		return $this->directory . '/' . $this->getFileName( $depositId );
	}

	private function getFileName( string $depositId ): string {
		return self::FILE_PREFIX . preg_replace( '/[^A-Za-z0-9._-]+/', '_', $depositId ) . '.json';
	}

	private function writeJsonFile( string $path, array $payload ): void {
		$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $json === false ) {
			throw new \RuntimeException( 'Unable to write Chariot json: ' . $path );
		}

		$json .= "\n";

		if ( file_exists( $path ) && file_get_contents( $path ) === $json ) {
			return;
		}

		if ( file_put_contents( $path, $json ) === false ) {
			throw new \RuntimeException( 'Unable to write Chariot json file: ' . $path );
		}
	}

}
