<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class VenmoPaymentProvider extends PaymentProvider {

	/**
	 * Add extra check for venmo email
	 * @param array $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		// re-fetch email info for venmo
		if ( !empty( $params['gateway_session_id'] ) && empty( $params['email'] ) ) {
			Logger::info( 'No email passed, fetch again with gateway_session_id: ' . $params['gateway_session_id'] );
			$donorDetails = $this->fetchCustomerData( $params['gateway_session_id'] );
			if ( $donorDetails ) {
				$params['email'] = $donorDetails->getEmail();
				$params['first_name'] = $donorDetails->getFirstName();
				$params['last_name'] = $donorDetails->getLastName();
				$params['phone'] = $donorDetails->getPhone();
			}
			if ( !$params['email'] ) {
				Logger::info( 'Braintree re-fetch email failed: Need to use Maintenance script to fetch data again with order_id ' . $params['order_id'] . ' and gateway_session_id ' . $params['gateway_session_id'] );
			}
		}
		return parent::createPayment( $params );
	}

	/**
	 * @param string $id
	 * @return DonorDetails
	 */
	public function fetchCustomerData( string $id ): DonorDetails {
		// venmo is using client side return for email, if not return for some reason, fetch again
		$rawResponse = $this->api->fetchCustomer( $id )['data']['node']['payerInfo'];
		Logger::info( 'Result from customer info fetch: ' . json_encode( $rawResponse ) );
		$donorDetails = new DonorDetails();
		if ( $rawResponse ) {
			$donorDetails->setUserName( $rawResponse['userName'] ?? null );
			$donorDetails->setCustomerId( $rawResponse['externalId'] ?? null );
			$donorDetails->setEmail( $rawResponse['email'] ?? null );
			$donorDetails->setFirstName( $rawResponse['firstName'] ?? null );
			$donorDetails->setLastName( $rawResponse['lastName'] ?? null );
			$donorDetails->setPhone( $rawResponse['phoneNumber'] ?? null );
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

	protected function getInvalidParams( array $params ): array {
		$invalidParams = parent::getInvalidParams( $params );
		if ( empty( $params['gateway_session_id'] ) && empty( $params['email'] ) ) {
			$invalidParams[] = 'gateway_session_id';
		}
		return $invalidParams;
	}
}
