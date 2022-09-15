<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Http\OutboundRequest;

/**
 * Sign Ingenico requests according to instructions at
 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/authentication.html
 */
class Authenticator {

	protected $keyId;
	protected $secret;

	public function __construct( string $keyId, string $secret ) {
		$this->keyId = $keyId;
		$this->secret = $secret;
	}

	public function signRequest( OutboundRequest $request ) {
		$toSign = $this->getStringToSign( $request );

		$signature = base64_encode(
			hash_hmac(
				'sha256',
				$toSign,
				$this->secret,
				true
			)
		);

		$authHeader = "GCS v1HMAC:{$this->keyId}:$signature";
		$request->setHeader( 'Authorization', $authHeader );
	}

	protected function getStringToSign( OutboundRequest $request ): string {
		$headers = $request->getHeaders();

		if ( empty( $headers['Content-Type'] ) ) {
			$contentType = '';
		} else {
			$contentType = $headers['Content-Type'];
		}

		$urlParts = parse_url( $request->getUrl() );
		$pathToSign = $urlParts['path'];
		if ( !empty( $urlParts['query'] ) ) {
			$pathToSign .= '?' . urldecode( $urlParts['query'] );
		}

		$toSign = $request->getMethod() . "\n"
			. $contentType . "\n"
			. $headers['Date'] . "\n"
			. $this->getGcsHeaders( $headers )
			. $pathToSign . "\n";

		return $toSign;
	}

	protected function getGcsHeaders( array $headers ): string {
		$canonicalizedHeaders = [];
		foreach ( $headers as $name => $value ) {
			if ( substr( $name, 0, 5 ) === 'X-GCS' ) {
				$value = preg_replace( '/\r?\n */', ' ', $value );
				$canonicalizedHeaders[] = strtolower( $name ) . ':' . trim( $value );
			}
		}
		if ( empty( $canonicalizedHeaders ) ) {
			return '';
		}
		sort( $canonicalizedHeaders );
		return implode( "\n", $canonicalizedHeaders ) . "\n";
	}
}
