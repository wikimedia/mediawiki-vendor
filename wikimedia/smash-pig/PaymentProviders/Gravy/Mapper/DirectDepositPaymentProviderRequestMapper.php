<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class DirectDepositPaymentProviderRequestMapper extends RequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$request = parent::mapToCreatePaymentRequest( $params );

		// getting the buyer details from ACH and not from our form
		unset( $request['buyer'] );

		if ( !isset( $params['recurring_payment_token'] ) ) {
			$payment_method = [
				'method' => 'trustly',
				'country' => $params['country'],
				'currency' => $params['currency'],
			];
			$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );
		}
		return $request;
	}
}
