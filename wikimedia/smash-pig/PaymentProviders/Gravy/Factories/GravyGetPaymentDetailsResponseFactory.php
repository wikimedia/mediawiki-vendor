<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyGetPaymentDetailsResponseFactory extends GravyCreatePaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new PaymentDetailResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof PaymentDetailResponse ) {
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
