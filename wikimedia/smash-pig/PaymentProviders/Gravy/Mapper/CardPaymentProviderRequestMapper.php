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

		// 3D-Secure parameters
		if ( isset( $params['browser_info'] ) ) {
			// This is a 3d-secure request. Pass through the browser_info array
			$request['browser_info'] = $params['browser_info'];
		}
		// This 3D-secure parameter is specific to Adyen
		if ( isset( $params['window_origin'] ) ) {
			$request['connection_options']['adyen-card']['window_origin'] = $params['window_origin'];
		}

		return $request;
	}
}
