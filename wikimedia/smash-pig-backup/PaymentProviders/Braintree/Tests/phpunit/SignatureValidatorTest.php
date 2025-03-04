<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Braintree\SignatureValidator;

class SignatureValidatorTest extends TestCase {

	protected $test_public_key;
	protected $test_private_key;
	protected $test_payload;
	protected $test_encoded_payload;

	protected function setUp(): void {
		parent::setUp();
		$this->test_public_key = "PUBICKEY123";
		$this->test_private_key = "PRIVATEKEY123";
		$this->test_payload = <<<XML
<SampleNotificaton>
We're just two lost souls
Swimming in a fish bowl
Year after year
Running over the same old ground
What have we found?
The same old fears
Wish you were here
Songwriters: Roger Waters / David Gilmour
</SampleNotificaton>
XML;
		$this->test_encoded_payload = base64_encode( $this->test_payload );
	}

	public function testAuthenticateValidCalculatedSignature() {
		// the HMAC(signature) was generated using the following:
		// hash_hmac( 'sha1', $this->test_encoded_payload, sha1( $this->test_private_key, true ) )
		$validSignature = 'PUBICKEY123|b5bdb89041fc1f3e0fd5f923ab1bbea451c63241';

		$signatureValidator = $this->getTestSignatureValidator();
		$result = $signatureValidator->parse( $validSignature, $this->test_encoded_payload );
		$this->assertEquals( $result, $this->test_payload );
	}

	public function testMissingPublicKeyConfigThrowsException() {
		$this->expectException( 'SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException' );
		$this->expectExceptionMessage( 'Public key required to use Signature Validator' );

		$invalidConfig = [
			'private-key' => $this->test_private_key
		];

		$signatureValidator = new SignatureValidator( $invalidConfig );
	}

	public function testMissingPrivateKeyConfigThrowsException() {
		$this->expectException( 'SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException' );
		$this->expectExceptionMessage( 'Private key required to use Signature Validator' );

		$invalidConfig = [
			'public-key' => $this->test_public_key,
		];

		$signatureValidator = new SignatureValidator( $invalidConfig );
	}

	public function testInvalidCalculatedSignatureThrowsException() {
		$this->expectException( 'SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException' );
		$this->expectExceptionMessage( 'Signature does not match payload - one has been modified' );

		$invalidTestSignature = "PUBICKEY123|no000valid000signature000ps";
		$testPayload = $this->test_encoded_payload;
		$signatureValidator = $this->getTestSignatureValidator();
		$signatureValidator->parse( $invalidTestSignature, $testPayload );
	}

	public function testEmptySignatureThrowsException() {
		$this->expectException( 'SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException' );
		$this->expectExceptionMessage( 'Signature cannot be empty' );

		$emptySignature = "";
		$signatureValidator = $this->getTestSignatureValidator();
		$signatureValidator->parse( $emptySignature, $this->test_payload );
	}

	public function testEmptyPayloadThrowsException() {
		$this->expectException( 'SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException' );
		$this->expectExceptionMessage( 'Payload cannot be empty' );

		$testSignature = "PUBICKEY123|sdafgr43rtgeaw34t5sgeaw34t5yre";
		$emptyPayload = "";
		$signatureValidator = $this->getTestSignatureValidator();
		$signatureValidator->parse( $testSignature, $emptyPayload );
	}

	public function testInvalidPayloadThrowsException() {
		$this->expectException( 'SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException' );
		$this->expectExceptionMessage( 'Payload contains illegal characters' );

		$testSignature = "PUBICKEY123|sdafgr43rtgeaw34t5sgeaw34t5yre";
		$invalidPayload = "@@@@sdfsfdasdf////^^^";
		$signatureValidator = $this->getTestSignatureValidator();
		$signatureValidator->parse( $testSignature, $invalidPayload );
	}

	public function testNonMatchingPublicKeyThrowsException() {
		$this->expectException( 'SmashPig\PaymentProviders\Braintree\Exceptions\SignatureValidatorException' );
		$this->expectExceptionMessage( 'No matching public key' );

		$testSignature = "PUBICKEY1234|sdafgr43rtgeaw34t5sgeaw34t5yre";
		$testPayload = $this->test_encoded_payload;
		$signatureValidator = $this->getTestSignatureValidator();
		$signatureValidator->parse( $testSignature, $testPayload );
	}

	/**
	 * @return SignatureValidator
	 */
	private function getTestSignatureValidator() {
		$config = [
			'public-key' => $this->test_public_key,
			'private-key' => $this->test_private_key
		];

		$signatureValidator = new SignatureValidator( $config );
		return $signatureValidator;
	}
}
