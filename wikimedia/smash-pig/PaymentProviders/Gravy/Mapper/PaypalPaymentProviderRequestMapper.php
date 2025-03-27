<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class PaypalPaymentProviderRequestMapper extends RequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$request = parent::mapToCreatePaymentRequest( $params );

		// getting the buyer details from Paypal and not from our form
		unset( $request['buyer'] );

		$method = $params['payment_submethod'];
		if ( empty( $method ) ) {
			$method = $params['payment_method'];
		}

		if ( !isset( $params['recurring_payment_token'] ) ) {
			$payment_method = [
				'method' => $this->mapPaymentMethodToGravyPaymentMethod( $method ),
				'country' => $params['country'],
				'currency' => $params['currency'],
			];
			$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );
		}

		if ( in_array( $request['payment_method']['method'], self::CAPTURE_ONLY_PAYMENT_METHOD ) ) {
			/**
			 * Defines the intent of a Gravy API call
			 *
			 * Available options:
			 * - `authorize` (Default): Optionally approves and then authorizes a
			 * transaction, but does not capture the funds.
			 * - `capture`: Optionally approves and then authorizes and captures the
			 * funds of the transaction.
			 */
			$request['intent'] = self::INTENT_CAPTURE;
		}
		return $request;
	}
}
