<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyCreatePaymentSessionResponseFactory extends GravyPaymentResponseFactory {

	protected static function createBasicResponse(): CreatePaymentSessionResponse {
		return new CreatePaymentSessionResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $rawResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof CreatePaymentSessionResponse ) {
			return;
		}
		$paymentResponse->setPaymentSession( $normalizedResponse['gateway_session_id'] );
	}
}
