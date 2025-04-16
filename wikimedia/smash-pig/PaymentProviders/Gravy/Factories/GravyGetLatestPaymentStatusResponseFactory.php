<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyGetLatestPaymentStatusResponseFactory extends GravyCreatePaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new PaymentProviderExtendedResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof PaymentProviderExtendedResponse ) {
			return;
		}
		self::setOrderId( $paymentResponse, $normalizedResponse );
		self::setPaymentDetails( $paymentResponse, $normalizedResponse );
		self::setRiskScores( $paymentResponse, $normalizedResponse );
		self::setRedirectURL( $paymentResponse, $normalizedResponse );
		self::setRecurringPaymentToken( $paymentResponse, $normalizedResponse );
		self::setPaymentSubmethod( $paymentResponse, $normalizedResponse );
		self::setDonorDetails( $paymentResponse, $normalizedResponse );
		self::setBackendProcessorAndId( $paymentResponse, $normalizedResponse );
		self::setPaymentOrchestrationReconciliationId( $paymentResponse, $normalizedResponse );
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function setOrderId( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !empty( $normalizedResponse['order_id'] ) ) {
			$paymentResponse->setOrderId( $normalizedResponse['order_id'] );
		}
	}
}
