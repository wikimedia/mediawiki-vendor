<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Listeners\ListenerSecurityException;

/**
 * Checks the HMAC signature Adyen adds to each webhook NotificationRequestItem.
 *
 * Adyen joins a fixed list of fields from the item with ':', signs that string
 * with HMAC-SHA256 using the hex key generated for the webhook in the Customer
 * Area, and sends the base64 encoded result in additionalData.hmacSignature.
 * Missing fields are signed as empty strings and nothing is escaped.
 *
 * Validate the item as decoded from the request body. AdyenMessage changes
 * signed fields (amount, merchantReference) when it builds a message, so the
 * signature won't match after that.
 *
 * See https://docs.adyen.com/development-resources/webhooks/secure-webhooks/verify-hmac-signatures/
 * and Adyen\Util\HmacSignature in adyen-php-api-library:
 * https://github.com/Adyen/adyen-php-api-library/tree/main
 */
class NotificationSignatureValidator {

	/**
	 * Adyen accounts config, keyed by merchant account code. Each account
	 * that sends webhooks needs a 'webhook-hmac-key'.
	 */
	private array $accounts;

	public function __construct( array $accounts ) {
		$this->accounts = $accounts;
	}

	/**
	 * @param array $item a NotificationRequestItem as decoded from the request body
	 * @throws ListenerSecurityException if the item does not carry a valid signature
	 */
	public function validateSignature( array $item ): void {
		$account = $item['merchantAccountCode'] ?? null;
		$key = is_string( $account ) ? ( $this->accounts[$account]['webhook-hmac-key'] ?? null ) : null;
		// An empty key would let anyone compute a valid signature, so treat
		// it the same as no key at all.
		if ( !is_string( $key ) || !ctype_xdigit( $key ) ) {
			throw new ListenerSecurityException( 'No valid webhook-hmac-key configured for merchant account' );
		}

		$signature = $item['additionalData']['hmacSignature'] ?? null;
		if ( !is_string( $signature ) || $signature === '' ) {
			throw new ListenerSecurityException( 'Notification has no hmacSignature' );
		}

		$expected = self::calculateSignature( $item, $key );
		if ( !hash_equals( $expected, $signature ) ) {
			throw new ListenerSecurityException( 'Notification hmacSignature does not match' );
		}
	}

	/**
	 * @param array $item a NotificationRequestItem as decoded from the request body
	 * @param string $hexKey HMAC key from the Customer Area, hex encoded
	 * @return string base64 encoded signature
	 * @throws ListenerSecurityException if a signed field has a type Adyen never sends
	 */
	public static function calculateSignature( array $item, string $hexKey ): string {
		$data = self::getSignedData( $item );
		return base64_encode( hash_hmac( 'sha256', $data, pack( 'H*', $hexKey ), true ) );
	}

	/**
	 * Build the string Adyen signs: the signed fields joined with ':', with
	 * missing fields as empty strings, as described in "Step 1: Construct the
	 * payload" at
	 * https://docs.adyen.com/development-resources/webhooks/secure-webhooks/verify-hmac-signatures/
	 *
	 * Adyen's schema for these webhooks has every signed field as a string,
	 * apart from amount.value, which is an integer:
	 * https://github.com/Adyen/adyen-openapi/blob/main/json/Webhooks-v1.json
	 * Any other type (e.g. an array, float, or boolean) didn't come
	 * from Adyen, and casting it would sign 'Array', 'INF' or '1', so we throw.
	 *
	 * @param array $item
	 * @return string
	 * @throws ListenerSecurityException if a signed field has a type Adyen never sends
	 */
	private static function getSignedData( array $item ): string {
		$amount = is_array( $item['amount'] ?? null ) ? $item['amount'] : [];
		$values = [
			$item['pspReference'] ?? null,
			$item['originalReference'] ?? null,
			$item['merchantAccountCode'] ?? null,
			$item['merchantReference'] ?? null,
			// Raw minor units, so a zero amount is signed as '0'
			$amount['value'] ?? null,
			$amount['currency'] ?? null,
			$item['eventCode'] ?? null,
			// The string 'true' or 'false'
			$item['success'] ?? null,
		];
		$strings = [];
		foreach ( $values as $value ) {
			if ( $value === null ) {
				// Missing field, signed as an empty string
				$strings[] = '';
			} elseif ( is_string( $value ) || is_int( $value ) ) {
				$strings[] = (string)$value;
			} else {
				throw new ListenerSecurityException( 'Notification has a signed field with a type Adyen never sends' );
			}
		}
		return implode( ':', $strings );
	}
}
