<?php
namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Http\HttpStatusValidator;
use Symfony\Component\HttpFoundation\Response;

class RestResponseValidator extends HttpStatusValidator {

	/**
	 * Status code 500 isn't exactly successful, but when they give us an errorCode along
	 * with it, we can handle that at a different level.
	 * @param array $parsedResponse
	 * @return bool
	 */
	public function shouldRetry( array $parsedResponse ): bool {
		if ( $parsedResponse['status'] == 500 ) {
			$body = $parsedResponse['body'];
			if ( $body ) {
				$decodedBody = json_decode( $body, true );
				if ( $decodedBody && !empty( $decodedBody['errorCode'] ) ) {
					return false;
				}
			}
		}
		return parent::shouldRetry( $parsedResponse );
	}

	protected function getSuccessCodes(): array {
		$codes = parent::getSuccessCodes();
		// Adyen uses 422 when an invalid card number or CVC is sent
		$codes[] = Response::HTTP_UNPROCESSABLE_ENTITY;
		return $codes;
	}
}
