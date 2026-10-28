<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

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
		if ( !empty( $currency ) && $currency_map && !array_key_exists( $currency, $currency_map ) ) {
			return 'currency';
		}
		return null;
	}

	protected function indicateMerchant( array $params, array &$apiParams ) {
		$currency_map = $this->merchantAccounts;
		if ( !empty( $params['currency'] ) && $currency_map && array_key_exists( $params['currency'], $currency_map ) ) {
			$apiParams['transaction']['merchantAccountId'] = $currency_map[$params['currency']];
		}
		return $apiParams;
	}

	protected function setMethodSpecificResponseFields(
		array $transaction, PaymentProviderExtendedResponse $response
	): void {
		$paymentMethodSnapshot = $transaction['paymentMethodSnapshot'] ?? null;
		if ( !$paymentMethodSnapshot ) {
			return;
		}
		$this->setBackendProcessorFields( $paymentMethodSnapshot, $response );

		$payer = $paymentMethodSnapshot['payer'] ?? null;
		if ( !$payer || !$response->getDonorDetails() ) {
			return;
		}
		$response->getDonorDetails()
			->setFirstName( $payer['firstName'] )
			->setLastName( $payer['lastName'] )
			->setEmail( $payer['email'] )
			->setPhone( $payer['phone'] ?? null );
	}

	protected function setBackendProcessorFields(
		array $paymentMethodSnapshot, PaymentProviderExtendedResponse $response
	) {
		if ( !$paymentMethodSnapshot ) {
			return;
		}
		$response->setBackendProcessor( 'paypal' );
		$response->setBackendProcessorTransactionId( $paymentMethodSnapshot['captureId'] ?? null );
		$response->setAuthID( $paymentMethodSnapshot['authorizationId'] ?? null );
		$response->setCaptureID( $paymentMethodSnapshot['captureId'] ?? null );
		if ( $paymentMethodSnapshot['payer'] ) {
			$response->setBackendProcessorContactID( $paymentMethodSnapshot['payer']['payerId'] ?? null );
		}
	}
}
