<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyApprovePaymentResponseFactory extends GravyPaymentResponseFactory {

	protected static function createBasicResponse(): ApprovePaymentResponse {
		return new ApprovePaymentResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof ApprovePaymentResponse ) {
			return;
		}

		self::setPaymentDetails( $paymentResponse, $normalizedResponse );
		self::setBackendProcessorAndId( $paymentResponse, $normalizedResponse );
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function setPaymentDetails( ApprovePaymentResponse $paymentResponse, array $normalizedResponse ): void {
		$paymentResponse->setGatewayTxnId( $normalizedResponse['gateway_txn_id'] );
	}
}
