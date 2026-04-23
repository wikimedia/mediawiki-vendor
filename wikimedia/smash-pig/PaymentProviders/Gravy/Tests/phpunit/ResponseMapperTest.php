<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class ResponseMapperTest extends BaseGravyTestCase {

	public function testMapToCreatePaymentResponseAuthDecline() {
		$rawResponse = $this->loadTestData( 'trustly-create-transaction-failed.json' );
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertFalse( $result['is_successful'] );
		$this->assertEquals( FinalStatus::FAILED, $result['status'] );
		$this->assertEquals( 'authorization_declined', $result['message'] );
	}

	public function testMapToCreatePaymentResponseAuthDeclineInsufficientFunds() {
		$rawResponse = $this->loadTestData( 'create-payment-response-insufficient-funds.json' );
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertFalse( $result['is_successful'] );
		$this->assertEquals( FinalStatus::FAILED, $result['status'] );
		$this->assertEquals( 'authorization_declined', $result['message'] );
		$this->assertEquals( 'insufficient_funds', $result['description'] );
	}

	/**
	 * Helper method to load JSON test data
	 */
	private function loadTestData( string $filename ): array {
		$filePath = __DIR__ . '/../Data/' . $filename;
		$jsonContent = file_get_contents( $filePath );
		return json_decode( $jsonContent, true );
	}
}
