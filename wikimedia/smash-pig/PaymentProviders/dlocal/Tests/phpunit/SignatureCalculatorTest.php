<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\dlocal\SignatureCalculator;

class SignatureCalculatorTest extends TestCase {

	public function testCalculateSignature(): void {
		$signatureCalculator = new SignatureCalculator();

		// generate the signature using test inputs
		$secret = "its_a_secret";
		$message = "test message";
		$calculatedSignature = $signatureCalculator->calculate( $message, $secret );

		$this->assertEquals( $this->getPreCalculatedHMACSHA256Signature(), $calculatedSignature );
	}

	/**
	 * Return a HMAC SHA256 signature generated using:
	 * `echo -n "test message" | openssl dgst -sha256 -hmac "its_a_secret" -hex`
	 *
	 * @return string
	 */
	private function getPreCalculatedHMACSHA256Signature(): string {
		return 'df8b183bbf9493d8fcfeb5cdc3f084bafcac5ce4155e8bb6b1b007cdeb21ebba';
	}

}
