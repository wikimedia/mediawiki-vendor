<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class VenmoPaymentProviderRequestMapper extends RedirectPaymentProviderRequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$params['payment_method'] = 'venmo';
		$request = parent::mapToCreatePaymentRequest( $params );

		// getting the buyer details from Venmo and not from our form
		unset( $request['buyer'] );

		return $request;
	}
}
