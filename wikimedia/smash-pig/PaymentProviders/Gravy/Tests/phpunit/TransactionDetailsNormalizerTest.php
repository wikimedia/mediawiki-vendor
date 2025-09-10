<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;
use SmashPig\PaymentProviders\Gravy\TransactionDetailsNormalizer;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class TransactionDetailsNormalizerTest extends BaseGravyTestCase {
	public function testNormalizeTransactionDetails(): void {
		$transactionDetailsNormalizer = new TransactionDetailsNormalizer();
		$paymentMethod = 'card';
		$transactionDetails = $this->getTransactionDetailsFixture();

		$transactionDetailsResponse = $transactionDetailsNormalizer->normalizeTransactionDetails(
			$paymentMethod,
			$transactionDetails
		);

		$this->assertInstanceOf( PaymentProviderResponse::class,
			$transactionDetailsResponse );
		$this->assertSame( FinalStatus::COMPLETE, $transactionDetailsResponse->getStatus() );
		$this->assertSame( 'capture_succeeded', $transactionDetailsResponse->getRawStatus() );
		$this->assertSame( 'b332ca0a-1dce-4ae6-b27b-04f70db8fae7', $transactionDetailsResponse->getGatewayTxnId() );
		$this->assertSame( '8110000000002017185', $transactionDetailsResponse->getBackendProcessorTransactionId() );
		$this->assertSame( 12.99, $transactionDetailsResponse->getAmount() );
		$this->assertSame( 'USD', $transactionDetailsResponse->getCurrency() );
	}

	public function testNormalizeTrustlyTransactionDetails(): void {
		$transactionDetailsNormalizer = new TransactionDetailsNormalizer();
		$paymentMethod = 'trustly';
		$transactionDetails = $this->getTransactionDetailsFixture();

		$transactionDetailsResponse = $transactionDetailsNormalizer->normalizeTransactionDetails(
			$paymentMethod,
			$transactionDetails
		);

		$this->assertInstanceOf( PaymentProviderResponse::class,
			$transactionDetailsResponse );
		$this->assertSame( FinalStatus::COMPLETE, $transactionDetailsResponse->getStatus() );
		$this->assertSame( 'capture_succeeded', $transactionDetailsResponse->getRawStatus() );
		$this->assertSame( 'b332ca0a-1dce-4ae6-b27b-04f70db8fae7', $transactionDetailsResponse->getGatewayTxnId() );
		$this->assertSame( '8110000000002017185', $transactionDetailsResponse->getBackendProcessorTransactionId() );
		$this->assertSame( 12.99, $transactionDetailsResponse->getAmount() );
		$this->assertSame( 'USD', $transactionDetailsResponse->getCurrency() );
	}

	/**
	 * Test with a different payment method
	 */
	public function testNormalizeTransactionDetailsWithDifferentPaymentMethod(): void {
		$transactionDetailsNormalizer = new TransactionDetailsNormalizer();
		$paymentMethod = 'paypal';
		$transactionDetails = $this->getTransactionDetailsFixture();
		// Modify the payment method in the fixture
		$transactionDetails['payment_method']['method'] = 'paypal';

		$transactionDetailsResponse = $transactionDetailsNormalizer->normalizeTransactionDetails(
			$paymentMethod,
			$transactionDetails
		);

		$this->assertInstanceOf( PaymentProviderResponse::class, $transactionDetailsResponse );
		$this->assertEquals( FinalStatus::COMPLETE, $transactionDetailsResponse->getStatus() );
		$this->assertEquals( 'paypal', $transactionDetailsResponse->getPaymentMethod() );
	}

	/**
	 * Test with a different transaction status
	 */
	public function testNormalizeTransactionDetailsWithDifferentStatus(): void {
		$transactionDetailsNormalizer = new TransactionDetailsNormalizer();
		$paymentMethod = 'card';
		$transactionDetails = $this->getTransactionDetailsFixture();
		// Modify the status in the fixture
		$transactionDetails['status'] = 'authorization_succeeded';

		$transactionDetailsResponse = $transactionDetailsNormalizer->normalizeTransactionDetails(
			$paymentMethod,
			$transactionDetails
		);

		$this->assertInstanceOf( PaymentProviderResponse::class, $transactionDetailsResponse );
		$this->assertEquals( FinalStatus::PENDING_POKE, $transactionDetailsResponse->getStatus() );
		$this->assertEquals( 'authorization_succeeded', $transactionDetailsResponse->getRawStatus() );
	}

	/**
	 * Test with an invalid payment method
	 */
	public function testNormalizeTransactionDetailsWithInvalidPaymentMethod(): void {
		$this->expectException( \InvalidArgumentException::class );

		$transactionDetailsNormalizer = new TransactionDetailsNormalizer();
		$paymentMethod = 'invalid_payment_method';
		$transactionDetails = $this->getTransactionDetailsFixture();

		$transactionDetailsNormalizer->normalizeTransactionDetails(
			$paymentMethod,
			$transactionDetails
		);
	}

	public function testNormalizeStripetokenTransactionDetails(): void {
		$transactionDetailsNormalizer = new TransactionDetailsNormalizer();
		$paymentMethod = 'card';
		$transactionDetails = json_decode( file_get_contents( __DIR__ . '/../Data/stripetoken-transaction-details-response.json' ),
			true );

		$transactionDetailsResponse = $transactionDetailsNormalizer->normalizeTransactionDetails(
			$paymentMethod,
			$transactionDetails
		);

		$this->assertInstanceOf( PaymentProviderResponse::class,
			$transactionDetailsResponse );
		$this->assertSame( FinalStatus::COMPLETE, $transactionDetailsResponse->getStatus() );
		$this->assertSame( 'capture_succeeded', $transactionDetailsResponse->getRawStatus() );
		$this->assertSame( 'random-transaction-id', $transactionDetailsResponse->getGatewayTxnId() );
		$this->assertSame( 'random_payment_service_transaction_id', $transactionDetailsResponse->getBackendProcessorTransactionId() );
		$this->assertSame( 'apple', $transactionDetailsResponse->getPaymentMethod() );
		$this->assertSame( 1.00, $transactionDetailsResponse->getAmount() );
		$this->assertSame( 'USD', $transactionDetailsResponse->getCurrency() );
	}

	protected function getTransactionDetailsFixture(): mixed {
		return json_decode( file_get_contents( __DIR__ . '/../Data/transaction-message-body.json' ),
			true );
	}
}
