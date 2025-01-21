<?php
namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\Core\Http\HttpStatusValidator;
use Symfony\Component\HttpFoundation\Response;

class RestResponseValidator extends HttpStatusValidator {

	public function shouldRetry( array $parsedResponse ): bool {
		$body = $parsedResponse['body'];
		// ingenico auth fail will print body with card info, need to remove from log
		if ( isset( $body['paymentResult']['payment']['paymentOutput']['cardPaymentMethodSpecificOutput']['card'] ) ) {
			unset( $body['paymentResult']['payment']['paymentOutput']['cardPaymentMethodSpecificOutput']['card'] );
		}
		$parsedResponse['body'] = $body;
		return parent::shouldRetry( $parsedResponse );
	}

	protected function getSuccessCodes(): array {
		$codes = parent::getSuccessCodes();
		// 404 is also a valid response in REST-ese
		$codes[] = Response::HTTP_NOT_FOUND;
		// Ingenico uses 402 to mean auth / capture rejected by the bank
		$codes[] = Response::HTTP_PAYMENT_REQUIRED;
		// Code 409 means the request is a duplicate. Not a raging success,
		// but we won't help things by re-trying
		$codes[] = Response::HTTP_CONFLICT;
		return $codes;
	}
}
