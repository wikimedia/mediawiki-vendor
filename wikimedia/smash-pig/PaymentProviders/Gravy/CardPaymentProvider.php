<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentSessionResponseFactory;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;

class CardPaymentProvider extends PaymentProvider {

	public function createPaymentSession(): CreatePaymentSessionResponse {
		$createPaymentSessionResponse = new CreatePaymentSessionResponse();
		try {
			// dispatch api call to external API
			$createPaymentSessionRawResponse = $this->api->createPaymentSession();
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromCreatePaymentSessionResponse( $createPaymentSessionRawResponse );
			$createPaymentSessionResponse = GravyCreatePaymentSessionResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( \UnexpectedValueException $e ) {
			// it threw an API exception!
			Logger::error( 'Processor failed to create new payment session with response:' . $e->getMessage() );
			GravyCreatePaymentSessionResponseFactory::handleException( $createPaymentSessionResponse, $e->getMessage(), $e->getCode() );
		}

		return $createPaymentSessionResponse;
	}

}
