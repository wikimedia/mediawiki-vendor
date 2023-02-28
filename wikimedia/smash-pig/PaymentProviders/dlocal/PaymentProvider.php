<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Core\ValidationError;
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
		$result = $this->api->cancelPayment( $gatewayTxnId );
		return DlocalCancelPaymentResponseFactory::fromRawResponse( $result );
	}

	/**
	 * @param array $params
	 *
	 * @return \SmashPig\PaymentProviders\Responses\PaymentDetailResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse {
		$result = $this->api->getPaymentStatus( $params['gateway_txn_id'] );
		return DlocalPaymentStatusResponseFactory::fromRawResponse( $result );
	}

	/**
	 * @throws ValidationException
	 */
	protected static function checkFields( $requiredFields, $input ) {
		$invalidFields = [];
		foreach ( $requiredFields as $field ) {
			if ( empty( $input[ $field ] ) ) {
				$invalidFields[$field] = 'required';
			}
		}

		if ( count( $invalidFields ) ) {
			throw new ValidationException( "Invalid input", $invalidFields );
		}
	}

	/**
	 * @param array $missingParams
	 * @param PaymentProviderResponse $paymentResponse
	 * @return void
	 */
	protected function addPaymentResponseValidationErrors(
		array $params, PaymentProviderResponse $paymentResponse
	): void {
		foreach ( $params as $param => $message ) {
			$paymentResponse->addValidationError(
				new ValidationError( $param, null, [], $message )
			);
		}
	}

}
