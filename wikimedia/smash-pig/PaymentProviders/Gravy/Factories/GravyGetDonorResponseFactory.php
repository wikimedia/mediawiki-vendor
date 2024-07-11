<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentData\Address;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyGetDonorResponseFactory extends GravyPaymentResponseFactory {

	protected static function createBasicResponse(): PaymentDetailResponse {
		return new PaymentDetailResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof PaymentDetailResponse ) {
			return;
		}

		self::setDonorDetails( $paymentResponse, $normalizedResponse );
	}

	protected static function setDonorDetails( PaymentDetailResponse $paymentResponse, array $normalizedResponse ) {
		$donorDetails = $normalizedResponse['donor_details'];

		$address = ( new Address )
						->setStreetAddress( $donorDetails['address']['address_line1'] ?? '' )
						->setPostalCode( $donorDetails['address']['postal_code'] ?? '' )
						->setStateOrProvinceCode( $donorDetails['address']['state'] ?? '' )
						->setCity( $donorDetails['address']['city'] ?? '' )
						->setCountryCode( $donorDetails['address']['country'] ?? '' );
		$details = ( new DonorDetails() )
						->setFirstName( $donorDetails['first_name'] ?? '' )
						->setLastName( $donorDetails['last_name'] ?? '' )
						->setEmail( $donorDetails['email_address'] ?? '' )
						->setPhone( $donorDetails['phone_number'] ?? '' )
						->setCustomerId( $donorDetails['processor_contact_id'] ?? '' )
						->setBillingAddress( $address );

		$paymentResponse->setDonorDetails( $details );
	}
}
