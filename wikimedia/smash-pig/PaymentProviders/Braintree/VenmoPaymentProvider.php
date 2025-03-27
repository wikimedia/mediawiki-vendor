<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\DonorDetails;

class VenmoPaymentProvider extends PaymentProvider {
	/**
	 * @param string $id
	 * @return DonorDetails
	 */
	public function fetchCustomerData( string $id ): DonorDetails {
		// venmo is using client side return for email, if not return for some reason, fetch again
		$rawResponse = $this->api->fetchCustomer( $id );
		$donorDetails = new DonorDetails();
		if ( isset( $rawResponse['data']['node']['payerInfo'] ) ) {
			$payerInfo = $rawResponse['data']['node']['payerInfo'];
			Logger::info( 'Result from customer info fetch: ' . json_encode( $payerInfo ) );
			$donorDetails->setEmail( $payerInfo['email'] ?? null );
			$donorDetails->setUserName( $payerInfo['userName'] ?? null );
			$donorDetails->setCustomerId( $payerInfo['externalId'] ?? null );
			$donorDetails->setFirstName( $payerInfo['firstName'] ?? null );
			$donorDetails->setLastName( $payerInfo['lastName'] ?? null );
			$donorDetails->setPhone( $payerInfo['phoneNumber'] ?? null );
		}
		return $donorDetails;
	}

	/**
	 * @param string $currency
	 * @return string|null
	 */
	protected function getInvalidCurrency( string $currency ) {
		// venmo only supported by USD account
		if ( !empty( $currency ) && $currency !== 'USD' ) {
			return 'currency';
		}
		return null;
	}

	/**
	 * @param array $params
	 * @param array &$apiParams
	 * @return array
	 */
	protected function indicateMerchant( array $params, array &$apiParams ) {
		// multi currency depends on different merchant, no need for venmo yet since only one account supported
		return $apiParams;
	}

	/**
	 * Make sure email exist for venmo donations
	 *
	 * @param array &$params
	 * @return void
	 */
	public function getMissingParams( array &$params ): void {
		// re-fetch email info for venmo
		if ( empty( $params['email'] ) ) {
			Logger::info( 'No email passed, fetch again with gateway_session_id: ' . $params['gateway_session_id'] );
			$donorDetails = $this->fetchCustomerData( $params['gateway_session_id'] );
			if ( $donorDetails->getEmail() ) {
				$params['email'] = $donorDetails->getEmail();
				$params['first_name'] = $donorDetails->getFirstName();
				$params['last_name'] = $donorDetails->getLastName();
				$params['phone'] = $donorDetails->getPhone();
			}
			if ( !$params['email'] ) {
				Logger::info( 'Braintree re-fetch email failed: Need to use Maintenance script to fetch data again with order_id ' . $params['order_id'] . ' and gateway_session_id ' . $params['gateway_session_id'] );
			}
		}
	}

	protected function getInvalidParams( array $params ): array {
		$invalidParams = parent::getInvalidParams( $params );
		if ( empty( $params['gateway_session_id'] ) && empty( $params['email'] ) ) {
			$invalidParams[] = 'gateway_session_id';
		}
		return $invalidParams;
	}
}
