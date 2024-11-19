<?php
namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Validators\Validator;

class GooglePayPaymentProvider extends PaymentProvider {
	public function validateInput( Validator $validator, array $params ): void {
		$validator->validateGoogleCreatePaymentInput( $params );
	}

	public function getPaymentRequest( array $params ): array {
		// map local params to external format, ideally only changing key names and minor input format transformations
		$gravyRequestMapper = new RequestMapper();

		$gravyCreatePaymentRequest = $gravyRequestMapper->mapToGoogleCreatePaymentRequest( $params );
		return $gravyCreatePaymentRequest;
	}
}
