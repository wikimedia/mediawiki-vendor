<?php

namespace SmashPig\PaymentProviders\Gravy\phpunit;

use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * This test class contains unit tests to verify the proper mapping and handling
 * of AVS (Address Verification System) and CVV (Card Verification Value) risk scores
 * for Gravy transactions.
 *
 * @group Gravy
 */
class RiskScoresTest extends BaseGravyTestCase {

	/**
	 * @dataProvider avsRiskScoreProvider
	 */
	public function testAvsRiskScoresAreCorrectlyMapped( $avsCode, $expectedAvsScore ): void {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );

		// Override the AVS response code in the test data
		$responseBody['avs_response_code'] = $avsCode;
		// Set a consistent CVV code to isolate AVS testing
		$responseBody['cvv_response_code'] = 'match';

		$gravyResponseMapper = new ResponseMapper();
		$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $responseBody );

		$response = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse', $response );
		$this->assertTrue( $response->isSuccessful() );

		$riskScores = $response->getRiskScores();

		if ( $expectedAvsScore !== null ) {
			$this->assertArrayHasKey( 'avs', $riskScores );
			$this->assertEquals( $expectedAvsScore, $riskScores['avs'] );
		} else {
			$this->assertArrayNotHasKey( 'avs', $riskScores );
		}
	}

	/**
	 * @dataProvider cvvRiskScoreProvider
	 */
	public function testCvvRiskScoresAreCorrectlyMapped( $cvvCode, $expectedCvvScore ): void {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );

		// Override the CVV response code in the test data
		$responseBody['cvv_response_code'] = $cvvCode;
		// Set a consistent AVS code to isolate CVV testing
		$responseBody['avs_response_code'] = 'match';

		$gravyResponseMapper = new ResponseMapper();
		$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $responseBody );

		$response = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse', $response );
		$this->assertTrue( $response->isSuccessful() );

		$riskScores = $response->getRiskScores();

		if ( $expectedCvvScore !== null ) {
			$this->assertArrayHasKey( 'cvv', $riskScores );
			$this->assertEquals( $expectedCvvScore, $riskScores['cvv'] );
		} else {
			$this->assertArrayNotHasKey( 'cvv', $riskScores );
		}
	}

	public function testBothAvsAndCvvRiskScoresAreMapped(): void {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );

		// Set both AVS and CVV codes
		$responseBody['avs_response_code'] = 'partial_match_address';
		$responseBody['cvv_response_code'] = 'no_match';

		$gravyResponseMapper = new ResponseMapper();
		$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $responseBody );

		$response = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse', $response );
		$this->assertTrue( $response->isSuccessful() );

		$riskScores = $response->getRiskScores();

		$this->assertArrayHasKey( 'avs', $riskScores );
		$this->assertArrayHasKey( 'cvv', $riskScores );
		$this->assertEquals( 75, $riskScores['avs'] );
		$this->assertEquals( 100, $riskScores['cvv'] );
	}

	public function testMissingAvsAndCvvCodesReturnEmptyScores(): void {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );

		// Remove AVS and CVV codes
		unset( $responseBody['avs_response_code'], $responseBody['cvv_response_code'] );

		$gravyResponseMapper = new ResponseMapper();
		$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $responseBody );

		$response = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse', $response );
		$this->assertTrue( $response->isSuccessful() );

		$riskScores = $response->getRiskScores();

		$this->assertSame( [], $riskScores );
	}

	/**
	 * Data provider for AVS risk score testing
	 */
	public function avsRiskScoreProvider(): array {
		return [
			[ 'partial_match_address', 75 ],
			[ 'partial_match_postcode', 75 ],
			[ 'partial_name_match', 75 ],
			[ 'match', 0 ],
			[ 'no_match', 100 ],
			[ 'unavailable', 50 ],
		];
	}

	/**
	 * Data provider for CVV risk score testing
	 */
	public function cvvRiskScoreProvider(): array {
		return [
			// Standard CVV scenarios
			[ 'match', 0 ],
			[ 'no_match', 100 ],
			[ 'not_provided', 100 ],
			[ 'unavailable', 50 ],
		];
	}
}
