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
	public function report(): string {
		$today = date( "Y-m-d", strtotime( "-1 days" ) );
		$response = $this->api->report( $today );

		if ( isset( $response['errors'] ) ) {
			return $response['errors'][0]['message'];
		}
		if ( $response['data']['report']['paymentLevelFees'] ) {
			return $response['data']['report']['paymentLevelFees']['url'];
		}
		return 'No result';
	}

}
