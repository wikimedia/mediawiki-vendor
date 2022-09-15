<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use SmashPig\Core\Http\OutboundRequest;
use SmashPig\PaymentProviders\Ingenico\Authenticator;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * See examples at
 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/authentication.html
 *
 * @group Ingenico
 */
class AuthenticatorTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Authenticator
	 */
	protected $authenticator;

	public function setUp() : void {
		$this->authenticator = new Authenticator(
			'5e45c937b9db33ae',
			'I42Zf4pVnRdroHfuHnRiJjJ2B6+22h0yQt/R3nZR8Xg='
		);
		parent::setUp();
	}

	/**
	 * Data taken from the 'minimal' example at the documentation URL in class comment
	 */
	public function testBasicSignature() {
		$request = new OutboundRequest( 'https://eu.sandbox.api-ingenico.com/v1/9991/tokens/123456789' );
		$request->setHeader( 'Date', 'Fri, 06 Jun 2014 13:39:43 GMT' );
		$this->authenticator->signRequest( $request );
		$headers = $request->getHeaders();
		$this->assertEquals(
			'GCS v1HMAC:5e45c937b9db33ae:J5LjfSBvrQNhu7gG0gvifZt+IWNDReGCmHmBmth6ueI=',
			$headers['Authorization']
		);
	}

	public function testEncodedQuery() {
		$request = new OutboundRequest( 'https://eu.sandbox.api-ingenico.com/v1/consumer/ANDR%C3%89E/?q=na%20me' );
		$request->setHeader( 'Date', 'Fri, 06 Jun 2014 13:39:43 GMT' );
		$this->authenticator->signRequest( $request );
		$headers = $request->getHeaders();
		$this->assertEquals(
			'GCS v1HMAC:5e45c937b9db33ae:x9S2hQmLhLTbpK0YdTuYCD8TB4D+Kf60tNW0Xw5Xls0=',
			$headers['Authorization']
		);
	}

	/**
	 * Ensure we correctly canonicalize and sort the X-GCS custom headers.
	 * Data taken from 'Full example with X-GCS headers'
	 */
	public function testGcsHeaders() {
		$request = new OutboundRequest( 'https://eu.sandbox.api-ingenico.com/v1/9991/tokens/123456789', 'DELETE' );
		$request->setHeader( 'Date', 'Fri, 06 Jun 2014 13:39:43 GMT' );
		$request->setHeader( 'Content-Type', 'application/json' );
		$request->setHeader( 'X-GCS-ClientMetaInfo', 'processed header value' );
		// Should replace newlines and spaces with a single space
		$request->setHeader( 'X-GCS-ServerMetaInfo', 'processed header
               value' );
		$request->setHeader( 'X-GCS-CustomerHeader', 'processed header value' );
		$this->authenticator->signRequest( $request );
		$headers = $request->getHeaders();
		$this->assertEquals(
			'GCS v1HMAC:5e45c937b9db33ae:jGWLz3ouN4klE+SkqO5gO+KkbQNM06Rric7E3dcfmqw=',
			$headers['Authorization']
		);
	}
}
