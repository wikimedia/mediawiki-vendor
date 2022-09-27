<?php

namespace SmashPig\PaymentProviders\Braintree;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;

class PaymentProvider implements IPaymentProvider {

	/**
	 * @var Api
	 */
	protected $api;

	public function __construct() {
		$config = Context::get()->getProviderConfiguration();
		$this->api = $config->object( 'api' );
	}

	/**
	 * @return CreatePaymentSessionResponse
	 */
	public function createPaymentSession(): CreatePaymentSessionResponse {
		$rawResponse = $this->api->createClientToken();
		$response = new CreatePaymentSessionResponse();
		$response->setRawResponse( $rawResponse );
		$response->setPaymentSession( $rawResponse['data']['createClientToken']['clientToken'] );
		return $response;
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}

	/**
	 * @param string $errorClass
	 * @return int
	 * https://graphql.braintreepayments.com/guides/making_api_calls/#understanding-responses
	 */
	protected function getErrorCode( string $errorClass ): int {
		switch ( $errorClass ) {
			case 'INTERNAL':
				return ErrorCode::INTERNAL_ERROR;
			case 'NOT_FOUND':
				return ErrorCode::METHOD_NOT_FOUND;
			case 'NOT_IMPLEMENTED':
				return ErrorCode::ACCOUNT_MISCONFIGURATION;
			case 'RESOURCE_LIMIT':
				return ErrorCode::EXCEEDED_LIMIT;
			case 'SERVICE_AVAILABILITY':
				return ErrorCode::SERVER_TIMEOUT;
			case 'UNSUPPORTED_CLIENT':
				return ErrorCode::UNEXPECTED_VALUE;
			case 'AUTHORIZATION':
			case 'AUTHENTICATION':
				return ErrorCode::BAD_SIGNATURE;
			case 'VALIDATION':
				return ErrorCode::VALIDATION;
			default:
				return ErrorCode::UNKNOWN;
		}
	}

	/**
	 * @param array $error
	 * @param string|null $debugMessage
	 * @return PaymentError|ValidationError
	 */
	protected function mapErrors( array $error, string $debugMessage = null ) {
		$defaultCode = ErrorCode::UNKNOWN;

		/**
		 *  https://developer.paypal.com/braintree/docs/reference/general/validation-errors/all
		 */
		$errorMap = [
			'82901' => ErrorCode::ACCOUNT_MISCONFIGURATION,
			'82903' => ErrorCode::ACCOUNT_MISCONFIGURATION,
			'82904' => ErrorCode::ACCOUNT_MISCONFIGURATION,
			'92906' => ErrorCode::ACCOUNT_MISCONFIGURATION
		];
		$mappedCode = $defaultCode;
		$logLevel = LogLevel::ERROR;
		if ( isset( $error['legacyCode'] ) && in_array( $error['legacyCode'], $errorMap ) ) {
			$mappedCode = $errorMap[$error['legacyCode']];
		}
		if ( isset( $error['errorClass'] ) ) {
			$mappedCode = $this->getErrorCode( $error['errorClass'] );
		}
		if ( $mappedCode == ErrorCode::VALIDATION ) {
			$validationField = ValidationErrorMapper::getValidationErrorField( $error['inputPath'] );
			return new ValidationError( $validationField, null, [], $debugMessage );
		}

		return new PaymentError(
			$mappedCode,
			json_encode( array_merge( $error, [ "message" => $debugMessage ] ) ),
			$logLevel
		);
	}
}
