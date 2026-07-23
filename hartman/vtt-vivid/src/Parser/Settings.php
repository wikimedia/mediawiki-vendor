<?php
namespace WebVTT\Parser;

use UnitEnum;

/**
 * Container for WebVTT settings.
 * This class stores parsed settings and provides methods to retrieve and validate them.
 */
class Settings {
	private array $settings;

	private const PERCENT_REGEX = '/^([\d]{1,3})(\.\d*)?%$/';

	public function __construct() {
		$this->settings = [];
	}

	// Set a key-value pair if the key is not already set
	public function set( string $key, mixed $value ): void {
		if ( !$this->has( $key ) && $value !== '' ) {
			$this->settings[$key] = $value;
		}
	}

	// Retrieve the value for a key, or a default value
	public function get( string $key, mixed $default = null, mixed $defaultKey = null ): mixed {
		if ( $defaultKey !== null && is_array( $default ) ) {
			return $this->has( $key ) ? $this->settings[$key] : ( $default[$defaultKey] ?? null );
		}
		return $this->has( $key ) ? $this->settings[$key] : $default;
	}

	// Check if a key exists
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->settings );
	}

	// Accept a setting if it's one of the given alternatives
	public function alt( string $key, mixed $value, array $alternatives ): void {
		if ( in_array( $value, $alternatives, true ) ) {
			$this->set( $key, $value );
		}
	}

	/**
	 * Accept a setting if it's a valid enum value.
	 *
	 * @param string $key
	 * @param string $value
	 * @param class-string<UnitEnum> $enumClass
	 */
	public function enum( string $key, string $value, string $enumClass ): bool {
		if ( !is_subclass_of( $enumClass, UnitEnum::class ) ) {
			return false;
		}

		if ( method_exists( $enumClass, 'tryFrom' ) ) {
			$enumValue = $enumClass::tryFrom( $value );
			if ( $enumValue !== null ) {
				$this->set( $key, $enumValue );
				return true;
			}
		}

		return false;
	}

	// Accept a setting if it's a valid integer
	public function integer( string $key, mixed $value ): void {
		if ( is_numeric( $value ) && preg_match( '/^-?\d+$/', (string)$value ) ) {
			$this->set( $key, (int)$value );
		}
	}

	// Accept a setting if it's a valid percentage
	public function percent( string $key, ?string $value ): bool {
		if ( $value !== null && $this->isValidPercentage( $value ) ) {
			$this->set( $key, (float)rtrim( $value, '%' ) );
			return true;
		}
		return false;
	}

	// Validate if a value is a proper percentage format (helper method)
	private function isValidPercentage( ?string $value ): bool {
		if ( $value !== null && preg_match( self::PERCENT_REGEX, $value ) ) {
			$floatValue = (float)rtrim( $value, '%' );
			return $floatValue >= 0 && $floatValue <= 100;
		}
		return false;
	}
}
