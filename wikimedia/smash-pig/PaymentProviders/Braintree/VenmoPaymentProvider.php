<?php

namespace SmashPig\PaymentProviders\Braintree;

class VenmoPaymentProvider extends PaymentProvider {

	protected function getInvalidCurrency( $currency ) {
		// venmo only supported by USD account
		if ( !empty( $currency ) && $currency !== 'USD' ) {
			return 'currency';
		}
		return null;
	}

	protected function indicateMerchant( array $params, array &$apiParams ) {
		// multi currency depends on different merchant, no need for venmo yet since only one account supported
		return $apiParams;
	}
}
