<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\ICancelablePaymentProvider;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class PaymentProvider implements IGetLatestPaymentStatusProvider, ICancelablePaymentProvider {
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * @var ProviderConfiguration
	 */
	protected $providerConfiguration;

	public function __construct() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}

	/**
	 * Cancel Payment which is authorized but not captured yet
	 * @param string $gatewayTxnId
	 *
	 * @return \SmashPig\PaymentProviders\Responses\CancelPaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelPayment( string $gatewayTxnId ): CancelPaymentResponse {
		try {
			$result = $this->api->cancelPayment( $gatewayTxnId );
			return DlocalCancelPaymentResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalCancelPaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * Get Payment detail which could contain recurring bt token
	 * @param string $gatewayTxnId
	 *
	 * @return \SmashPig\PaymentProviders\Responses\PaymentDetailResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentDetail( string $gatewayTxnId ): PaymentDetailResponse {
		try {
			$result = $this->api->getPaymentDetail( $gatewayTxnId );
			return DlocalCreatePaymentResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalCreatePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * @param array $params
	 * recurring charge
	 * @return \SmashPig\PaymentProviders\Responses\CreatePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPaymentFromToken( array $params ): CreatePaymentResponse {
		try {
			$result = $this->api->createPaymentFromToken( $params );
			$response = DlocalCreatePaymentResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalCreatePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
		return $response;
	}

	/**
	 * @param array $params
	 *
	 * @return \SmashPig\PaymentProviders\Responses\PaymentDetailResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse {
		try {
			$result = $this->api->getPaymentStatus( $params['gateway_txn_id'] );
			return DlocalPaymentStatusResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalPaymentStatusResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * @throws ValidationException
	 */
	protected static function checkFields( $requiredFields, $input ) {
		$invalidFields = [];
		foreach ( $requiredFields as $field ) {
			if ( empty( $input[$field] ) ) {
				$invalidFields[$field] = 'required';
			}
		}

		if ( count( $invalidFields ) ) {
			throw new ValidationException( "Invalid input", $invalidFields );
		}
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $error
	 * @return void
	 */
	protected static function handleValidationException( PaymentProviderResponse $paymentResponse, array $error ): void {
		self::addPaymentResponseValidationErrors( $error, $paymentResponse );
		$paymentResponse->setStatus( FinalStatus::FAILED );
		$paymentResponse->setSuccessful( false );
	}

	/**
	 * @param array $missingParams
	 * @param PaymentProviderResponse $paymentResponse
	 * @return void
	 */
	protected static function addPaymentResponseValidationErrors(
		array $params, PaymentProviderResponse $paymentResponse
	): void {
		foreach ( $params as $param => $message ) {
			$paymentResponse->addValidationError(
				new ValidationError( $param, null, [], $message )
			);
		}
	}
}
