<?php

namespace SmashPig\PaymentProviders\Gravy;

class GravyHelper {

	/**
	 * Gravy sends over a payment_service_definition_id property with each payment in the
	 * format of $processor-$paymentMethod, e.g. adyen-card, so we can extract the backend
	 * payment processor from that field using this small convenience method.
	 *
	 * @param string $paymentServiceDefinitionId
	 * @return string
	 */
	public static function extractProcessorNameFromServiceDefinitionId( string $paymentServiceDefinitionId ): string {
		// Check if the hyphen exists in the payment service definition id
		if ( str_contains( $paymentServiceDefinitionId, '-' ) ) {
			return explode( '-', $paymentServiceDefinitionId )[0];
		}
		// Return the input if no hyphen is found. We don't want to mess with it if it's not as expected.
		return $paymentServiceDefinitionId;
	}

}
