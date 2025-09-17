<?php

namespace SmashPig\Core\Helpers;

class Base62Helper {

	/**
	 * Decode Base62 (alphabet 0-9A-Za-z) to UUID v4/v8 format
	 * e.g. "1w24hGOdCSFLtsgBQr2jKh" -> "3f9c958c-ee57-4121-a79e-408946b27077"
	 */
	public static function toUuid( string $s ): string {
		$hex = self::toHex( $s );
		// Left-pad to 32 hex chars (128 bits), then format as UUID 8-4-4-4-12
		$hex = str_pad( $hex, 32, '0', STR_PAD_LEFT );
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 12, 4 ),
			substr( $hex, 16, 4 ),
			substr( $hex, 20 )
		);
	}

	/**
	 * Convert Base62 (alphabet 0-9A-Za-z) to lowercase hex string (no 0x, no dashes).
	 * Pure-PHP (no GMP/BCMath): repeated division base conversion.
	 */
	private static function toHex( string $s ): string {
		$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$fromBase = 62;
		$toBase   = 16;

		// Map input chars to digit array
		$digits = [];
		for ( $i = 0, $n = strlen( $s ); $i < $n; $i++ ) {
			$pos = strpos( $alphabet, $s[$i] );
			if ( $pos === false ) {
				throw new InvalidArgumentException( "Invalid character '{$s[$i]}' for provided Base62 alphabet." );
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

}
