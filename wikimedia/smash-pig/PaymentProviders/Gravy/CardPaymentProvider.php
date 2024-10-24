<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentSessionResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Validators\Validator;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\ValidationException;

class CardPaymentProvider extends PaymentProvider implements IPaymentProvider {

	public function createPaymentSession() : CreatePaymentSessionResponse {
		$sessionResponse = new CreatePaymentSessionResponse();
		try {
			// dispatch api call to external API using mapped params
			$sessionResponse = new CreatePaymentSessionResponse();
			$rawResponse = $this->api->createPaymentSession();

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

	/**
	 * @param array $params [gateway_session_id, amount, currency]
	 * for payment from secure fields, required parameters are:
	 * * gateway_session_id
	 * * amount
	 * * currency
	 * * order_id
	 * * email
	 * * first_name
	 * * last_name
	 *
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ) : CreatePaymentResponse {
		$createPaymentResponse = new createPaymentResponse();
		try {
			// extract out the validation of input out to a separate class
			$validator = new Validator();

			if ( isset( $params['recurring_payment_token'] ) ) {
				$validator->validateCreatePaymentFromTokenInput( $params );
			} else {
				$validator->validateCreatePaymentInput( $params );
			}

			// map local params to external format, ideally only changing key names and minor input format transformations
			$gravyRequestMapper = new RequestMapper();

			$gravyCreatePaymentRequest = $gravyRequestMapper->mapToCardCreatePaymentRequest( $params );

			// dispatch api call to external API using mapped params
			$rawGravyCreatePaymentResponse = $this->api->createPayment( $gravyCreatePaymentRequest );

			// normalize gravy response
			$gravyResponseMapper = $this->getResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $rawGravyCreatePaymentResponse );

			// populate our standard response object from the normalized response
			// this could be extracted out to a factory as we do for dlocal
			$createPaymentResponse = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );

		}  catch ( ValidationException $e ) {
			// it threw an exception!
			GravyCreatePaymentResponseFactory::handleValidationException( $createPaymentResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception that isn't validation!
			Logger::error( 'Processor failed to create new payment with response:' . $e->getMessage() );
			GravyCreatePaymentResponseFactory::handleException( $createPaymentResponse, $e->getMessage(), $e->getCode() );
		}

		return $createPaymentResponse;
	}

}
