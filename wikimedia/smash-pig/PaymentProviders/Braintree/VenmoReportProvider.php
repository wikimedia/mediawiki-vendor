<?php

namespace SmashPig\PaymentProviders\Braintree;

/**
 * This class allows testing connectivity with the Braintree GraphQL endpoint.
 * See GetReport script under PaymentProviders/Braintree/Maintenance.
 */
class VenmoReportProvider extends PaymentProvider {
	/**
	 * date must have YYYY-MM-DD format
	 * @return string
	 */
	public function report( $date ): string {
		if ( empty( $date ) ) {
			$date = date( 'Y-m-t', strtotime( 'last day of previous month' ) );
		}
		$response = $this->api->report( $date );

		if ( isset( $response['errors'] ) ) {
			return $response['errors'][0]['message'];
		}
		if ( $response['data']['report']['paymentLevelFees'] ) {
			return $response['data']['report']['paymentLevelFees']['url'];
		}
		return 'No result';
	}

}
