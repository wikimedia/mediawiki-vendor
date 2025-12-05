<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentProviders\Gravy\PaymentMethod;

class RedirectPaymentProviderRequestMapper extends RequestMapper {
	/**
	 * @return array
	 */
	public function mapToCreatePaymentRequest( array $params ): array {
		$request = parent::mapToCreatePaymentRequest( $params );

		$paymentMethodString = $params['payment_submethod'];
		if ( empty( $paymentMethodString ) ) {
			$paymentMethodString = $params['payment_method'];
		}

		// Convert string to our PaymentMethod enum (null if invalid)
		$paymentMethodEnum = PaymentMethod::tryFrom( strtolower( $paymentMethodString ) );
		if ( $paymentMethodEnum === null ) {
			throw new \UnexpectedValueException( "Invalid PaymentMethod passed in: {$paymentMethodString}" );
		}

		if ( !isset( $params['recurring_payment_token'] ) ) {
			$payment_method = [
				'method' => $paymentMethodEnum->toGravyValue(),
				'country' => $params['country'],
				'currency' => $params['currency'],
			];
			$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );
		}

		if ( in_array( $paymentMethodEnum->toGravyValue(), self::CAPTURE_ONLY_PAYMENT_METHOD ) ) {
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
