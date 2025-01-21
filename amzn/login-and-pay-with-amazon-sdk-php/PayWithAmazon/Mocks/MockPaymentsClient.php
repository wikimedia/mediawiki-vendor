<?php namespace PayWithAmazon\Mocks;

use PayWithAmazon\PaymentsClientInterface;

/**
 * Stubs out the functionality of the PaymentsClient class from the Login and
 * Pay with Amazon SDK.  TODO: replace with PHPUnit method return mocks when
 * Jenkins is running new enough PHPUnit.  Only mocking the stuff we use.
 */
class MockPaymentsClient extends MockBaseClient implements PaymentsClientInterface {

	public function authorize( $requestParameters = array() ) {
		return $this->fakeCall( 'authorize', $requestParameters );
	}

	public function authorizeOnBillingAgreement( $requestParameters = array() ) {
		return $this->fakeCall( 'authorizeOnBillingAgreement', $requestParameters );
	}
	
	public function cancelOrderReference( $requestParameters = array() ) {
		return $this->fakeCall( 'cancelOrderReference', $requestParameters );
	}

	public function capture( $requestParameters = array() ) {
		return $this->fakeCall( 'capture', $requestParameters );
	}

	public function charge( $requestParameters = array() ) {
		return $this->fakeCall( 'charge', $requestParameters );
	}

	public function closeAuthorization( $requestParameters = array() ) {
		return $this->fakeCall( 'closeAuthorization', $requestParameters );
	}

	public function closeBillingAgreement( $requestParameters = array() ) {
		return $this->fakeCall( 'capture', $requestParameters );
	}

	public function closeOrderReference( $requestParameters = array() ) {
		return $this->fakeCall( 'closeOrderReference', $requestParameters );
	}

	public function confirmBillingAgreement( $requestParameters = array() ) {
		return $this->fakeCall( 'confirmBillingAgreement', $requestParameters );
	}

	public function confirmOrderReference( $requestParameters = array() ) {
		return $this->fakeCall( 'confirmOrderReference', $requestParameters );
	}

	public function createOrderReferenceForId( $requestParameters = array() ) {
		return $this->fakeCall( 'createOrderReferenceForId', $requestParameters );
	}

	public function getAuthorizationDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getAuthorizationDetails', $requestParameters );
	}

	public function getBillingAgreementDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getBillingAgreementDetails', $requestParameters );
	}

	public function getCaptureDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getCaptureDetails', $requestParameters );
	}

	public function getOrderReferenceDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getOrderReferenceDetails', $requestParameters );
	}

	public function getProviderCreditDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getProviderCreditDetails', $requestParameters );
	}

	public function getProviderCreditReversalDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getProviderCreditReversalDetails', $requestParameters );
	}

	public function getRefundDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getRefundDetails', $requestParameters );
	}

	public function getServiceStatus( $requestParameters = array() ) {
		return $this->fakeCall( 'getServiceStatus', $requestParameters );
	}

	public function getUserInfo( $access_token ) {

	}

	public function refund( $requestParameters = array() ) {
		return $this->fakeCall( 'refund', $requestParameters );
	}

	public function reverseProviderCredit( $requestParameters = array() ) {
		return $this->fakeCall( 'reverseProviderCredit', $requestParameters );
	}

	public function setBillingAgreementDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'setBillingAgreementDetails', $requestParameters );

	}

	public function setOrderReferenceDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'setOrderReferenceDetails', $requestParameters );
	}

	public function validateBillingAgreement( $requestParameters = array() ) {
		return $this->fakeCall( 'validateBillingAgreement', $requestParameters );
	}

}
