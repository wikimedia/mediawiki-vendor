<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class GooglePayPaymentProviderRequestMapper extends RequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$nameParts = explode( ' ', $params['full_name'], 2 );
		$params['first_name'] = $nameParts[0];
		$params['last_name'] = $nameParts[1] ?? '';
		$request_params = parent::mapToCreatePaymentRequest( $params );
		$request_params['payment_method'] = array_merge( $request_params['payment_method'], [
			'method' => 'googlepay',
			'token' => $params['payment_token'],
			'card_suffix' => $params['card_suffix'],
			'card_scheme' => $params['card_scheme'],
		] );
		return $request_params;
	}
}
