<?php
namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentSessionResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\ApplePayPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ApplePayPaymentProviderResponseMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Validators\ApplePayPaymentProviderValidator;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;

class ApplePayPaymentProvider extends PaymentProvider {
	public function createPaymentSession( array $params ) : CreatePaymentSessionResponse {
		$sessionResponse = new CreatePaymentSessionResponse();
		try {
			// extract out the validation of input out to a separate class
			$validator = $this->getValidator();

			$validator->validateCreateSessionInput( $params );

			// dispatch api call to external API using mapped params
			$sessionResponse = new CreatePaymentSessionResponse();
			$rawResponse = $this->api->createPaymentSession( $params, 'apple' );

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = $this->getResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromCreatePaymentSessionResponse( $rawResponse );

			$sessionResponse = GravyCreatePaymentSessionResponseFactory::fromNormalizedResponse( $normalizedResponse );
			return $sessionResponse;
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Processor failed to create new payment session with response:' . $e->getMessage() );
			GravyCreatePaymentSessionResponseFactory::handleException( $sessionResponse, $e->getMessage(), $e->getCode() );
		}

		return $sessionResponse;
	}

	protected function getValidator(): ApplePayPaymentProviderValidator {
		return new ApplePayPaymentProviderValidator();
	}

	protected function getRequestMapper(): RequestMapper {
		return new ApplePayPaymentProviderRequestMapper();
	}

	protected function getResponseMapper(): ApplePayPaymentProviderResponseMapper {
		return new ApplePayPaymentProviderResponseMapper();
	}
}
