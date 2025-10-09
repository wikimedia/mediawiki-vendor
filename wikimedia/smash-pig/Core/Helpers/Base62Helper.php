<?php

namespace SmashPig\Core\Helpers;

class Base62Helper {

	/**
	 * Decode Base62 (alphabet 0-9A-Za-z) to UUID v4/v8 format
	 * e.g. "1w24hGOdCSFLtsgBQr2jKh" -> "3f9c958c-ee57-4121-a79e-408946b27077"
	 */
	public static function toUuid( string $s ): string {
		// 1) strict decode
		$hex = self::toHex( $s );
		$hex = str_pad( $hex, 32, '0', STR_PAD_LEFT );
		$bytes = hex2bin( $hex );
		if ( self::isRfc4122Variant( $bytes ) && self::isVersion4or8( $bytes ) ) {
			return self::hexToUuid( $hex );
		}

		// 2) lenient repair: drop exactly one leading non-zero digit (after any zeros) and retry
		$repaired = self::dropFirstNonZeroDigit( $s );
		if ( $repaired !== null ) {
			$hex2 = self::toHex( $repaired );
			$hex2 = str_pad( $hex2, 32, '0', STR_PAD_LEFT );
			$bytes2 = hex2bin( $hex2 );
			if ( self::isRfc4122Variant( $bytes2 ) && self::isVersion4or8( $bytes2 ) ) {
				// (Optional) log normalization: $s -> $repaired
				return self::hexToUuid( $hex2 );
			}
		}

		// 3) fall back to strict result (or throw if you prefer strict-only behavior)
		return self::hexToUuid( $hex );
	}

	/**
	 * Convert Base62 (alphabet 0-9A-Za-z) to lowercase hex string (no 0x, no dashes).
	 * Pure-PHP (no GMP/BCMath): repeated division base conversion.
	 */
	private static function toHex( string $s ): string {
		$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$fromBase = 62;
		$toBase   = 16;

		// Trim redundant leading zero digits
		$s = ltrim( $s, '0' );
		if ( $s === '' ) {
			$s = '0';
		}

		// Map input chars to digit array
		$digits = [];
		for ( $i = 0, $n = strlen( $s ); $i < $n; $i++ ) {
			$pos = strpos( $alphabet, $s[$i] );
			if ( $pos === false ) {
				throw new \InvalidArgumentException( "Invalid character '{$s[$i]}' for Base62 alphabet." );
			}
			$digits[] = $pos;
		}

		// Repeated division algorithm: convert from base 62 to base 16
		$result = [];
		while ( count( $digits ) > 0 ) {
			$quotient = [];
			$remainder = 0;
			foreach ( $digits as $d ) {
				$acc = $remainder * $fromBase + $d;
				$q = intdiv( $acc, $toBase );
				$remainder = $acc % $toBase;
				if ( !empty( $quotient ) || $q !== 0 ) {
					$quotient[] = $q;
				}
			}
			$result[] = $remainder;     // collect base-16 digit (least significant first)
			$digits = $quotient;
		}

		if ( empty( $result ) ) {
			return '0';
		}

		// Map base-16 digits to hex chars (reverse to most-significant-first)
		$hexChars = '0123456789abcdef';
		$hex = '';
		for ( $i = count( $result ) - 1; $i >= 0; $i-- ) {
			$hex .= $hexChars[$result[$i]];
		}

		// Ensure even length
		if ( ( strlen( $hex ) & 1 ) === 1 ) {
			$hex = '0' . $hex;
		}
		return $hex;
	}

	/** Format 32-char hex (lowercase) as UUID 8-4-4-4-12 */
	private static function hexToUuid( string $hex ): string {
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 12, 4 ),
			substr( $hex, 16, 4 ),
			substr( $hex, 20 )
		);
	}

	/** RFC-4122 variant: byte 8 must have high bits 10xxxxxx */
	private static function isRfc4122Variant( string $bytes ): bool {
		if ( strlen( $bytes ) !== 16 ) {
			return false;
		}
		return ( ( ord( $bytes[8] ) & 0xC0 ) === 0x80 );
	}

	/** Allow v4 or v8 (per your comment). */
	private static function isVersion4or8( string $bytes ): bool {
		if ( strlen( $bytes ) !== 16 ) {
			return false;
		}
		$version = ( ord( $bytes[6] ) & 0xF0 ) >> 4;
		return $version === 4 || $version === 8;
	}

	/**
	 * If the first non-zero digit exists, drop exactly that one digit.
	 * Example: "023US..." -> "03US..." (leading zeros will be trimmed in toHex()).
	 * Returns repaired string or null if no repair applies.
	 */
	private static function dropFirstNonZeroDigit( string $s ): ?string {
		$i = 0;
		$len = strlen( $s );
		while ( $i < $len && $s[$i] === '0' ) {
			$i++;
		}
		if ( $i < $len ) {
			return substr( $s, 0, $i ) . substr( $s, $i + 1 );
		}
		return null;
	}
}
