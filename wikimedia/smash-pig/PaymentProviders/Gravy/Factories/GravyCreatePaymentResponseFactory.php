<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentData\Address;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyCreatePaymentResponseFactory extends GravyPaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new CreatePaymentResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof CreatePaymentResponse ) {
			return;
		}

		self::setPaymentDetails( $paymentResponse, $normalizedResponse );
		self::setRiskScores( $paymentResponse, $normalizedResponse );
		self::setRedirectURL( $paymentResponse, $normalizedResponse );
		self::setRecurringPaymentToken( $paymentResponse, $normalizedResponse );
		self::setPaymentSubmethod( $paymentResponse, $normalizedResponse );
		self::setDonorDetails( $paymentResponse, $normalizedResponse );
		self::setBackendProcessorAndId( $paymentResponse, $normalizedResponse );
		self::setPaymentOrchestrationReconciliationId( $paymentResponse, $normalizedResponse );
		self::setSuspectedFraud( $paymentResponse, $normalizedResponse );
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function setRecurringPaymentToken( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !empty( $normalizedResponse['recurring_payment_token'] ) ) {
			$paymentResponse->setRecurringPaymentToken( $normalizedResponse['recurring_payment_token'] );
		}
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function setPaymentDetails( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		$paymentResponse->setGatewayTxnId( $normalizedResponse['gateway_txn_id'] );
		$paymentResponse->setAmount( $normalizedResponse['amount'] );
		$paymentResponse->setCurrency( $normalizedResponse['currency'] );
	}

	/**
	 * @param PaymentProviderResponse $createPaymentResponse
	 * @param array $rawResponse
	 * @return void
	 */
	protected static function setRedirectURL( PaymentProviderResponse $createPaymentResponse, array $normalizedResponse ): void {
		if ( !empty( $normalizedResponse['redirect_url'] ) ) {
			$createPaymentResponse->setRedirectUrl( $normalizedResponse['redirect_url'] );
		}
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $rawResponse
	 * @return void
	 */
	protected static function setPaymentSubmethod( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		$paymentResponse->setPaymentMethod( $normalizedResponse['payment_method'] );
		$paymentResponse->setPaymentSubmethod( $normalizedResponse['payment_submethod'] );
	}

	protected static function setDonorDetails( PaymentProviderResponse $paymentResponse, array $normalizedResponse ) {
		$donorDetails = $normalizedResponse['donor_details'] ?? [];

		$address = ( new Address() )
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
			->setUserName( $donorDetails['username'] ?? '' )
			->setBillingEmail( $donorDetails['billing_email'] ?? '' )
			->setBillingAddress( $address );
		$paymentResponse->setProcessorContactID( $donorDetails['processor_contact_id'] ?? '' );
		$paymentResponse->setDonorDetails( $details );
	}

	protected static function setRiskScores( PaymentProviderResponse $paymentResponse, array $normalizedResponse ) {
		$paymentResponse->setRiskScores( $normalizedResponse['risk_scores'] );
	}

	protected static function setSuspectedFraud( CreatePaymentResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !empty( $normalizedResponse['is_suspected_fraud'] ) ) {
			$paymentResponse->setSuspectedFraud( $normalizedResponse['is_suspected_fraud'] );
		}
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param string|null $statusDetail
	 * @param string|null $errorCode
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function addPaymentFailureError( PaymentProviderResponse $paymentResponse, ?string $statusDetail = 'Unknown error', ?string $errorCode = null, array $normalizedResponse = [] ): void {
		parent::addPaymentFailureError( $paymentResponse, $statusDetail, $errorCode, $normalizedResponse );
		if ( !empty( $normalizedResponse['is_suspected_fraud'] ) ) {
			$paymentResponse->setSuspectedFraud( true );
		}
	}

}
