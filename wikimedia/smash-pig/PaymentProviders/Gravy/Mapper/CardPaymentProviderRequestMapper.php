<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class CardPaymentProviderRequestMapper extends RequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$request = parent::mapToCreatePaymentRequest( $params );
		$payment_method = [];
		if ( isset( $params['gateway_session_id'] ) ) {
			$payment_method = [
				'method' => 'checkout-session',
				'id' => $params['gateway_session_id'],
			];
		}

		$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );

		return $request;
	}
}
