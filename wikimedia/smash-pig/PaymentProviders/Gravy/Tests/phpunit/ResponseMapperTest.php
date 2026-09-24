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

	public function testMapMotoTransactionSetsMotoFields() {
		$rawResponse = $this->loadTestData( 'moto-transaction-capture-message.json' )['target'];
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertTrue( $result['is_moto'] );
		$this->assertSame( $rawResponse['metadata'], $result['moto_metadata'] );
	}

	public function testMapNonMotoTransactionDoesNotSetMotoFields() {
		// This transaction carries metadata of its own, which should be left alone
		// because it is not a moto gift.
		$rawResponse = $this->loadTestData( 'successful-transaction-capture-message.json' )['target'];
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertArrayNotHasKey( 'is_moto', $result );
		$this->assertArrayNotHasKey( 'moto_metadata', $result );
	}

	public function testMapMotoTransactionWithoutMetadata() {
		$rawResponse = $this->loadTestData( 'moto-transaction-capture-message.json' )['target'];
		unset( $rawResponse['metadata'] );
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertTrue( $result['is_moto'] );
		$this->assertSame( [], $result['moto_metadata'] );
	}

	public function testMapPaymentResponseSetsBackendProcessorContactIdWhenAdditionalIdentifiersPresent() {
		$rawResponse = $this->buildPaypalPaymentResponse();
		$rawResponse['additional_identifiers'] = [ 'payer_id' => 'PAYER123' ];
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertSame( 'PAYER123', $result['donor_details']['backend_processor_contact_id'] );
	}

	public function testMapPaymentResponseDoesNotSetBackendProcessorContactIdWhenAdditionalIdentifiersMissing() {
		$rawResponse = $this->buildPaypalPaymentResponse();
		unset( $rawResponse['additional_identifiers'] );
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertArrayNotHasKey( 'backend_processor_contact_id', $result['donor_details'] );
	}

	public function testMapPaymentResponseDoesNotSetBackendProcessorContactIdWhenAdditionalIdentifiersEmpty() {
		$rawResponse = $this->buildPaypalPaymentResponse();
		$rawResponse['additional_identifiers'] = [];
		$mapper = new ResponseMapper();
		$result = $mapper->mapFromPaymentResponse( $rawResponse );

		$this->assertArrayNotHasKey( 'backend_processor_contact_id', $result['donor_details'] );
	}

	/**
	 * Builds a minimal successful paypal payment response, since paypal is
	 * the only method in ResponseMapper::METHODS_WITH_PAYERID.
	 */
	private function buildPaypalPaymentResponse(): array {
		return [
			'id' => 'test-transaction-id',
			'status' => 'succeeded',
			'amount' => 1000,
			'currency' => 'USD',
			'external_identifier' => '12345.1',
			'payment_method' => [
				'method' => 'paypal',
			],
			'buyer' => [
				'id' => 'buyer-id',
				'billing_details' => [
					'first_name' => 'Testy',
					'last_name' => 'McTest',
					'email_address' => 'test@example.com',
					'phone_number' => '555-1212',
				],
			],
		];
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
