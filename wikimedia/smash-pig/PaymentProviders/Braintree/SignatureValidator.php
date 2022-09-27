<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException;

/**
 * Class SignatureValidator
 * @package SmashPig\PaymentProviders\Braintree
 *
 * This component calculates and verifies the payload HMACs sent over by Braintree.
 * The algo used is HMAC-SHA1.
 *
 * Most of the behaviour in this class has been extracted from the Official Braintree SDK
 * here https://github.com/braintree/braintree_php
 * I deliberately kept the fn names the same here as they are in the SDK so that if we ever need
 * to update this code, it's easier to compare the two implementations. Common language and all...
 *
 * How it works:
 * When Braintree sends over a wehbook notification, they send us three things.
 * - public key
 * - HMAC (https://en.wikipedia.org/wiki/HMAC)
 * - base64 encoded payload
 *
 * Firstly, we compare the public key sent over with the public key associated with our API user.
 * If they match, then we move on the HMAC authentication step. If we calculate the
 * same HMAC as the one Braintree sends over using the same payload and private key(explained below),
 * we've authenticated the payload.
 *
 * Quick note on Signatures vs HMAC:
 * ------------------------------------------------------------------------------------
 * Although the word 'Signature' is used a lot in this code and across the Braintree SDK,
 * we're not actually verifying digital signatures in the true sense here. A digital
 * signature is something that is typically produced using a private key, and then
 * verified using a public key, asymmetric. Check out https://en.wikipedia.org/wiki/Digital_signature
 *
 * HMACs are different and use a symmetric key or a shared secret to authenticate.
 * In this case, Braintree uses our API user private key to generate a HMAC-SHA1 authentication code
 * and sends that along with the payload. To verify the HMAC on our side, we also calculate a hash
 * using both the payload and the private key of our API user account, and compare.
 * This approach confirms two things, the sender of the payload *is* Braintree, and the contents of
 * the message have not been tampered with over the wire.
 * -------------------------------------------------------------------------------------
 *
 */
class SignatureValidator {

	/**
	 * Config required fields:
	 * - 'public-key' // I much prefer _ over - but in the words of mando, this is the way.
	 * - 'private-key'
	 * @var array
	 */
	private $config = [];

	/**
	 * @param array $config
	 */
	public function __construct( array $config ) {
		if ( !array_key_exists( 'public-key', $config ) ) {
			throw new SignatureValidatorException( "Public key required to use Signature Validator" );
		}
		if ( !array_key_exists( 'private-key', $config ) ) {
			throw new SignatureValidatorException( "Private key required to use Signature Validator" );
		}

		$this->config = $config;
	}

	/**
	 * This method does the following:
	 * - validate the signature and payload contents
	 * - authenticate the signature(HMAC)
	 * - decode and return the raw payload.
	 *
	 * @param string $signature
	 * @param string $payload
	 *
	 * @return string
	 * @throws SignatureValidatorException
	 */
	public function parse( string $signature, string $payload ): string {
		if ( empty( $signature ) ) {
			throw new SignatureValidatorException( "Signature cannot be empty" );
		}

		if ( empty( $payload ) ) {
			throw new SignatureValidatorException( "Payload cannot be empty" );
		}

		if ( preg_match( "/[^A-Za-z0-9+=\/\n]/", $payload ) === 1 ) {
			throw new SignatureValidatorException( "Payload contains illegal characters" );
		}

		$this->validateSignature( $signature, $payload );
		$result = base64_decode( $payload );
		return $result;
	}

	/**
	 * @param string $signatureString
	 * @param string $payload
	 * @throws SignatureValidatorException
	 */
	private function validateSignature( string $signatureString, string $payload ): void {
		// split out the public key from the HMAC
		$signaturePairs = preg_split( "/&/", $signatureString );
		// confirm the public key matches our own
		$signature = $this->matchingSignature( $signaturePairs );
		if ( !$signature ) {
			throw new SignatureValidatorException( "No matching public key" );
		}

		// calculate HMAC using payload and private key.
		// note: in the Braintree SDK they also try $payload . "\n"
		// if the $payload alone fails so I added that here too.
		if (
			!$this->payloadMatches( $signature, $payload ) &&
			!$this->payloadMatches( $signature, $payload . "\n" )
		) {
			throw new SignatureValidatorException( "Signature does not match payload - one has been modified" );
		}
	}

	/**
	 * Check public key matches our own
	 *
	 * @param array $signaturePairs
	 * @return string|null
	 */
	private function matchingSignature( array $signaturePairs ): ?string {
		foreach ( $signaturePairs as $pair ) {
			$components = preg_split( "/\|/", $pair );
			if ( $components[0] == $this->config['public-key'] ) {
				return $components[1];
			}
		}

		return null;
	}

	/**
	 * Calculate HMAC using payload and private key compare securely.
	 *
	 * @param string $signature
	 * @param string $payload
	 * @return bool
	 */
	private function payloadMatches( string $signature, string $payload ): bool {
		$payloadSignature = $this->hexDigestSha1( $payload,  $this->config['private-key'] );
		return $this->secureCompare( $signature, $payloadSignature );
	}

	/**
	 * Digest creates an HMAC-SHA1 hash for encrypting messages
	 *
	 * @param string $message
	 * @param string $key
	 * @return string
	 */
	private function hexDigestSha1( string $message, string $key ): string {
		return hash_hmac( 'sha1', $message, sha1( $key, true ) );
	}

	/**
	 * This is a fancy way to compare strings that prevents
	 * timing attacks. https://codahale.com/a-lesson-in-timing-attacks/
	 *
	 * @param string $left
	 * @param string $right
	 * @return bool
	 */
	private function secureCompare( string $left, string $right ): bool {
		if ( strlen( $left ) != strlen( $right ) ) {
			return false;
		}

		$leftBytes = unpack( "C*", $left );
		$rightBytes = unpack( "C*", $right );

		$result = 0;
		for ( $i = 1; $i <= count( $leftBytes ); $i++ ) {
			$result = $result | ( $leftBytes[$i] ^ $rightBytes[$i] );
		}
		return $result == 0;
	}

}
