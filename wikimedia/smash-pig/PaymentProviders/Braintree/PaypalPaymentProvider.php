<?php

namespace SmashPig\PaymentProviders\Braintree;

class PaypalPaymentProvider extends PaymentProvider {

	/**
	 * @var array
	 */
	private $merchantAccounts;

	public function __construct( array $options ) {
		parent::__construct();
		$this->merchantAccounts = $options['merchant-accounts'] ?? null;
	}

	protected function getInvalidCurrency( $currency ) {
		$currency_map = $this->merchantAccounts;
		if ( !empty( $currency ) && $currency_map && !array_key_exists( $currency,  $currency_map ) ) {
			return 'currency';
		}
		return null;
	}

	protected function indicateMerchant( array $params, array &$apiParams ) {
		$currency_map = $this->merchantAccounts;
		if ( !empty( $params['currency'] ) && $currency_map && array_key_exists( $params['currency'],  $currency_map ) ) {
			$apiParams['transaction']['merchantAccountId'] = $currency_map[$params['currency']];
		}
		return $apiParams;
	}
}
