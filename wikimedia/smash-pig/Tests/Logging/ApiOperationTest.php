<?php

namespace SmashPig\Tests\Logging;

use PHPUnit\Framework\TestCase;
use SmashPig\Core\Logging\ApiOperation;
use SmashPig\Core\Logging\ApiOperationAttribute;

/**
 * @group Timings
 */
class ApiOperationTest extends TestCase {

	public function testEnumCasesHaveCorrectValues(): void {
		$this->assertSame( 'authorize', ApiOperation::AUTHORIZE->value );
		$this->assertSame( 'capture', ApiOperation::CAPTURE->value );
		$this->assertSame( 'refund', ApiOperation::REFUND->value );
		$this->assertSame( 'cancel', ApiOperation::CANCEL->value );
		$this->assertSame( 'getPaymentMethods', ApiOperation::GET_PAYMENT_METHODS->value );
		$this->assertSame( 'getPaymentStatus', ApiOperation::GET_PAYMENT_STATUS->value );
		$this->assertSame( 'getPaymentDetails', ApiOperation::GET_PAYMENT_DETAILS->value );
		$this->assertSame( 'createSession', ApiOperation::CREATE_SESSION->value );
		$this->assertSame( 'deleteToken', ApiOperation::DELETE_TOKEN->value );
		$this->assertSame( 'getSavedPaymentDetails', ApiOperation::GET_SAVED_PAYMENT_DETAILS->value );
		$this->assertSame( 'getRefund', ApiOperation::GET_REFUND->value );
		$this->assertSame( 'getReportExecution', ApiOperation::GET_REPORT_EXECUTION->value );
		$this->assertSame( 'getReportDownloadUrl', ApiOperation::GET_REPORT_DOWNLOAD_URL->value );
		$this->assertSame( 'getPaymentServiceDefinition', ApiOperation::GET_PAYMENT_SERVICE_DEFINITION->value );
		$this->assertSame( 'deleteData', ApiOperation::DELETE_DATA->value );
		$this->assertSame( 'verifyUpiId', ApiOperation::VERIFY_UPI_ID->value );
	}

	/**
	 * Helper to get the ApiOperation from a method's #[ApiOperationAttribute] attribute
	 */
	private function getOperationFromMethod( string $className, string $methodName ): ?ApiOperation {
		$reflectionMethod = new \ReflectionMethod( $className, $methodName );
		$attributes = $reflectionMethod->getAttributes( ApiOperationAttribute::class );

		if ( empty( $attributes ) ) {
			return null;
		}

		return $attributes[0]->newInstance()->operation;
	}

	/**
	 * @dataProvider adyenMethodsProvider
	 */
	public function testAdyenApiMethodsHaveCorrectAttributes( string $method, ApiOperation $expected ): void {
		$operation = $this->getOperationFromMethod( 'SmashPig\PaymentProviders\Adyen\Api', $method );
		$this->assertSame( $expected, $operation, "Method $method should have operation {$expected->value}" );
	}

	public static function adyenMethodsProvider(): array {
		return [
			[ 'createPaymentFromEncryptedDetails', ApiOperation::AUTHORIZE ],
			[ 'createPaymentFromToken', ApiOperation::AUTHORIZE ],
			[ 'createBankTransferPaymentFromCheckout', ApiOperation::AUTHORIZE ],
			[ 'createSEPABankTransferPayment', ApiOperation::AUTHORIZE ],
			[ 'createACHDirectDebitPayment', ApiOperation::AUTHORIZE ],
			[ 'createGooglePayPayment', ApiOperation::AUTHORIZE ],
			[ 'createApplePayPayment', ApiOperation::AUTHORIZE ],
			[ 'approvePayment', ApiOperation::CAPTURE ],
			[ 'refundPayment', ApiOperation::REFUND ],
			[ 'cancel', ApiOperation::CANCEL ],
			[ 'cancelAutoRescue', ApiOperation::CANCEL ],
			[ 'getPaymentMethods', ApiOperation::GET_PAYMENT_METHODS ],
			[ 'getPaymentDetails', ApiOperation::GET_PAYMENT_DETAILS ],
			[ 'getSavedPaymentDetails', ApiOperation::GET_SAVED_PAYMENT_DETAILS ],
			[ 'deleteDataForPayment', ApiOperation::DELETE_DATA ],
			[ 'createApplePaySession', ApiOperation::CREATE_SESSION ],
		];
	}

	/**
	 * @dataProvider gravyMethodsProvider
	 */
	public function testGravyApiMethodsHaveCorrectAttributes( string $method, ApiOperation $expected ): void {
		$operation = $this->getOperationFromMethod( 'SmashPig\PaymentProviders\Gravy\Api', $method );
		$this->assertSame( $expected, $operation, "Method $method should have operation {$expected->value}" );
	}

	public static function gravyMethodsProvider(): array {
		return [
			[ 'createPayment', ApiOperation::AUTHORIZE ],
			[ 'createPaymentSession', ApiOperation::CREATE_SESSION ],
			[ 'approvePayment', ApiOperation::CAPTURE ],
			[ 'refundTransaction', ApiOperation::REFUND ],
			[ 'cancelTransaction', ApiOperation::CANCEL ],
			[ 'getTransaction', ApiOperation::GET_PAYMENT_STATUS ],
			[ 'deletePaymentToken', ApiOperation::DELETE_TOKEN ],
			[ 'getRefund', ApiOperation::GET_REFUND ],
			[ 'getReportExecutionDetails', ApiOperation::GET_REPORT_EXECUTION ],
			[ 'generateReportDownloadUrl', ApiOperation::GET_REPORT_DOWNLOAD_URL ],
			[ 'getPaymentServiceDefinition', ApiOperation::GET_PAYMENT_SERVICE_DEFINITION ],
		];
	}

	/**
	 * @dataProvider dlocalMethodsProvider
	 */
	public function testDlocalApiMethodsHaveCorrectAttributes( string $method, ApiOperation $expected ): void {
		$operation = $this->getOperationFromMethod( 'SmashPig\PaymentProviders\dlocal\Api', $method );
		$this->assertSame( $expected, $operation, "Method $method should have operation {$expected->value}" );
	}

	public static function dlocalMethodsProvider(): array {
		return [
			[ 'getPaymentMethods', ApiOperation::GET_PAYMENT_METHODS ],
			[ 'cardAuthorizePayment', ApiOperation::AUTHORIZE ],
			[ 'verifyUpiId', ApiOperation::VERIFY_UPI_ID ],
			[ 'collectDirectBankTransfer', ApiOperation::AUTHORIZE ],
			[ 'redirectPayment', ApiOperation::AUTHORIZE ],
			[ 'redirectHostedPayment', ApiOperation::AUTHORIZE ],
			[ 'createPaymentFromToken', ApiOperation::AUTHORIZE ],
			[ 'getPaymentDetail', ApiOperation::GET_PAYMENT_DETAILS ],
			[ 'capturePayment', ApiOperation::CAPTURE ],
			[ 'makeRecurringCardPayment', ApiOperation::AUTHORIZE ],
			[ 'getPaymentStatus', ApiOperation::GET_PAYMENT_STATUS ],
			[ 'cancelPayment', ApiOperation::CANCEL ],
			[ 'refundPayment', ApiOperation::REFUND ],
		];
	}

	/**
	 * @dataProvider paypalMethodsProvider
	 */
	public function testPaypalApiMethodsHaveCorrectAttributes( string $method, ApiOperation $expected ): void {
		$operation = $this->getOperationFromMethod( 'SmashPig\PaymentProviders\PayPal\Api', $method );
		$this->assertSame( $expected, $operation, "Method $method should have operation {$expected->value}" );
	}

	public static function paypalMethodsProvider(): array {
		return [
			[ 'createPaymentSession', ApiOperation::CREATE_SESSION ],
			[ 'doExpressCheckoutPayment', ApiOperation::AUTHORIZE ],
			[ 'createRecurringPaymentsProfile', ApiOperation::AUTHORIZE ],
			[ 'getExpressCheckoutDetails', ApiOperation::GET_PAYMENT_DETAILS ],
			[ 'manageRecurringPaymentsProfileStatusCancel', ApiOperation::CANCEL ],
			[ 'refundPayment', ApiOperation::REFUND ],
		];
	}

	/**
	 * @dataProvider braintreeMethodsProvider
	 */
	public function testBraintreeApiMethodsHaveCorrectAttributes( string $method, ApiOperation $expected ): void {
		$operation = $this->getOperationFromMethod( 'SmashPig\PaymentProviders\Braintree\Api', $method );
		$this->assertSame( $expected, $operation, "Method $method should have operation {$expected->value}" );
	}

	public static function braintreeMethodsProvider(): array {
		return [
			[ 'authorizePaymentMethod', ApiOperation::AUTHORIZE ],
			[ 'captureTransaction', ApiOperation::CAPTURE ],
			[ 'refundPayment', ApiOperation::REFUND ],
			[ 'createClientToken', ApiOperation::CREATE_SESSION ],
			[ 'deletePaymentMethodFromVault', ApiOperation::DELETE_TOKEN ],
			[ 'deleteCustomer', ApiOperation::DELETE_DATA ],
		];
	}
}
