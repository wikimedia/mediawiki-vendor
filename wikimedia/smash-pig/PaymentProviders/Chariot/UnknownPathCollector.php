<?php

namespace SmashPig\PaymentProviders\Chariot;

class UnknownPathCollector {

	private const TYPE_DEPOSIT = 'deposit';
	private const TYPE_DONATION = 'donation';

	/**
	 * @var array<string,array<string,array<string,mixed>>>
	 */
	private array $unknownsByType = [
		self::TYPE_DEPOSIT => [],
		self::TYPE_DONATION => [],
	];

	public function scan( array $payload, array $knownPaths ): void {
		$this->scanValue( $payload, '', $knownPaths, null );
	}

	public function scanDeposit( array $payload, array $knownPaths ): void {
		$this->scanValue( $payload, '', $knownPaths, self::TYPE_DEPOSIT );
	}

	public function scanDonation( array $payload, array $knownPaths ): void {
		$this->scanValue( $payload, '', $knownPaths, self::TYPE_DONATION );
	}

	public function getUnknowns(): array {
		return $this->getUnknownDepositPaths() + $this->getUnknownDonationPaths();
	}

	public function getUnknownDepositPaths(): array {
		return $this->getUnknownsForType( self::TYPE_DEPOSIT );
	}

	public function getUnknownDonationPaths(): array {
		return $this->getUnknownsForType( self::TYPE_DONATION );
	}

	private function getUnknownsForType( string $type ): array {
		ksort( $this->unknownsByType[$type] );
		return $this->unknownsByType[$type];
	}

	private function scanValue( mixed $value, string $path, array $knownPaths, ?string $type ): void {
		if ( is_array( $value ) ) {
			if ( $this->isListArray( $value ) ) {
				$listPath = $path === '' ? '[]' : $path;
				if ( !in_array( $listPath, $knownPaths, true ) ) {
					$this->noteUnknownPath( $listPath, $value[0] ?? '', $type );
				}

				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						foreach ( $item as $key => $itemValue ) {
							$childPath = $listPath . '[].' . $key;
							$this->scanValue( $itemValue, $childPath, $knownPaths, $type );
						}
					}
				}

				return;
			}

			if ( $path !== '' && !in_array( $path, $knownPaths, true ) ) {
				$this->noteUnknownPath( $path, $value, $type );
			}

			foreach ( $value as $key => $child ) {
				$childPath = $path === '' ? (string)$key : $path . '.' . $key;
				$this->scanValue( $child, $childPath, $knownPaths, $type );
			}

			return;
		}

		if ( $path !== '' && !in_array( $path, $knownPaths, true ) ) {
			$this->noteUnknownPath( $path, $value, $type );
		}
	}

	/**
	 * @param string $path
	 * @param mixed $sample
	 * @param string $type
	 *
	 * @return void
	 */
	private function noteUnknownPath( string $path, mixed $sample, string $type ): void {
		$this->noteUnknownPathInCollection(
			$type,
			$path,
			$sample
		);
	}

	private function noteUnknownPathInCollection( string $type, string $path, mixed $sample ): void {
		$sample = $this->sampleValue( $sample );
		if ( !isset( $this->unknownsByType[$type][$path] ) ) {
			$this->unknownsByType[$type][$path] = [
				'path' => $path,
				'count' => 0,
				'sample' => $sample,
			];
		}
		if ( $sample && !$this->unknownsByType[$type][$path]['sample'] ) {
			// Prefer meaningful sample.
			$this->unknownsByType[$type][$path]['sample'] = $sample;
		}

		$this->unknownsByType[$type][$path]['count']++;
	}

	/**
	 * @param mixed $value
	 *
	 * @return array|bool|string|null
	 */
	private function sampleValue( mixed $value ): bool|array|string|null {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( $value === null ) {
			return null;
		}

		return (string)$value;
	}

	private function isListArray( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

}
