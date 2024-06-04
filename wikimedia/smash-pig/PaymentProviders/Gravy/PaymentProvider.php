<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class PaymentProvider implements IPaymentProvider {

	public function createPayment( array $params ) : CreatePaymentResponse {
		try {
			// create our standard response object from the normalized response
			$createPaymentResponse = new createPaymentResponse();

			// extract out the validation of input out to a separate class
			$validator = new Validator();
			if ( $validator->createPaymentInputIsValid( $params ) ) {
				// map local params to external format, ideally only changing key names and minor input format transformations
				$gravyRequestMapper = new RequestMapper();
				$gravyCreatePaymentRequest = $gravyRequestMapper->mapToCreatePaymentRequest( $params );

				// dispatch api call to external API using mapped params
				$api = new Api();
				$rawGravyCreatePaymentResponse = $api->createPayment( $gravyCreatePaymentRequest );

				// map the response from the external format back to our normalized structure.
				$gravyResponseMapper = new ResponseMapper();
				$normalizedResponse = $gravyResponseMapper->mapFromCreatePaymentResponse( $rawGravyCreatePaymentResponse );

				// populate our standard response object from the normalized response
				// this could be extracted out to a factory as we do for dlocal
				$createPaymentResponse->setStatus( $normalizedResponse['status'] );
			} else {
				// it failed!
				$createPaymentResponse->setStatus( 'Failed' );
			}
		} catch ( \Exception $e ) {
			// it threw an exception!
			$createPaymentResponse->setStatus( 'Failed' );
		}

		return $createPaymentResponse;
	}

	public function approvePayment( array $params ) : ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}

}
