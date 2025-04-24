<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentProviders\Gravy\PaymentMethod;

class PaypalPaymentProviderRequestMapper extends RequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$request = parent::mapToCreatePaymentRequest( $params );

		// getting the buyer details from Paypal and not from our form
		unset( $request['buyer'] );

		if ( !isset( $params['recurring_payment_token'] ) ) {
			$payment_method = [
				'method' => PaymentMethod::PAYPAL,
				'country' => $params['country'],
				'currency' => $params['currency'],
			];
			$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );
		}
		return $request;
	}
}
