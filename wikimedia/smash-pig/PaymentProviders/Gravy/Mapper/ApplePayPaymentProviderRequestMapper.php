<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class ApplePayPaymentProviderRequestMapper extends RequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$request_params = parent::mapToCreatePaymentRequest( $params );
		$request_params['payment_method'] = array_merge( $request_params['payment_method'], [
			'method' => 'applepay',
			'token' => json_decode( $params['payment_token'], true ),
		] );
		return $request_params;
	}
}
