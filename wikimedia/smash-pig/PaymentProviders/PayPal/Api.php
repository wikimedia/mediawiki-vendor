<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Http\OutboundRequest;
use SmashPig\Core\Logging\Logger;

class Api {

	/**
	 * @var string API Endpoint.
	 */
	protected $endpoint;

	/**
	 * @var string Paypal's API USER param.
	 */
	protected $user;

	/**
	 * @var string Paypal's API PWD param.
	 */
	protected $password;

	/**
	 * @var string Path to API Certificate file.
	 */
	protected $certificate_path;

	/**
	 * @var string Paypal's VERSION param.
	 */
	protected $version;

	/**
	 * @param array $params required keys 'endpoint', 'user', 'password', 'certificate_path', and 'version'
	 */
	public function __construct( array $params ) {
		$this->endpoint = $params[ 'endpoint' ];
		$this->user = $params[ 'user' ];
		$this->password = $params[ 'password' ];
		$this->certificate_path = $params[ 'certificate_path' ];
		$this->version = $params[ 'version' ];
	}

	/**
	 * Base-level API call method. All calls should come through here.
	 *
	 * @param array $params
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function makeApiCall( array $params ) {
		$requestParams = array_merge( $this->getDefaultRequestParams(), $params );
		$request = new OutboundRequest( $this->endpoint, 'POST' );
		$request->setCertPath( $this->certificate_path );
		$request->setBody( http_build_query( $requestParams ) );
		$response = $request->execute();
		Logger::debug( "Response from API call: " . json_encode( $response ) );
		parse_str( $response['body'], $result );
		ExceptionMapper::throwOnPaypalError( $response['body'] );
		return $result;
	}

	/**
	 * Doc link: https://developer.paypal.com/api/nvp-soap/set-express-checkout-nvp/
	 *
	 * @param array $params
	 * @return array
	 */
	public function createPaymentSession( array $params ) {
		$requestParams = [
			'VERSION' => 204,
			'METHOD' => 'SetExpressCheckout',
			'RETURNURL' => $params['return_url'],
			'CANCELURL' => $params['cancel_url'],
			'REQCONFIRMSHIPPING' => 0,
			'NOSHIPPING' => 1,
			'LOCALECODE' => $params['language'],
			'L_PAYMENTREQUEST_0_AMT0' => $params['amount'],
			'L_PAYMENTREQUEST_0_DESC0' => $params['description'],
			'PAYMENTREQUEST_0_AMT' => $params['amount'],
			'PAYMENTREQUEST_0_CURRENCYCODE' => $params['currency'],
			'PAYMENTREQUEST_0_CUSTOM' => $params['order_id'],
			'PAYMENTREQUEST_0_DESC' => $params['description'],
			'PAYMENTREQUEST_0_INVNUM' => $params['order_id'],
			'PAYMENTREQUEST_0_ITEMAMT' => $params['amount'],
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
			'SOLUTIONTYPE' => 'Mark'
		];

		if ( $params['recurring'] ) {
			$requestParams['L_BILLINGTYPE0'] = 'RecurringPayments';
			$requestParams['L_BILLINGAGREEMENTDESCRIPTION0'] = $params['description'];
		}

		return $this->makeApiCall( $requestParams );
	}

	/**
	 * Doc link: https://developer.paypal.com/api/nvp-soap/do-express-checkout-payment-nvp/
	 *
	 * @param array $params
	 * @return array
	 */
	public function doExpressCheckoutPayment( array $params ) {
		$requestParams = [
			'METHOD' => 'DoExpressCheckoutPayment',
			'TOKEN' => $params['gateway_session_id'],
			'PAYERID' => $params['processor_contact_id'],
			'PAYMENTREQUEST_0_AMT' => $params['amount'],
			'PAYMENTREQUEST_0_CURRENCYCODE' => $params['currency'],
			'PAYMENTREQUEST_0_CUSTOM' => $params['order_id'],
			'PAYMENTREQUEST_0_INVNUM' => $params['order_id'],
			'PAYMENTREQUEST_0_ITEMAMT' => $params['amount'],
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
		];
		if ( !empty( $params['description'] ) ) {
			$requestParams['PAYMENTREQUEST_0_DESC'] = $params['description'];
		}

		return $this->makeApiCall( $requestParams );
	}

	/**
	 * Doc link: https://developer.paypal.com/api/nvp-soap/create-recurring-payments-profile-nvp/
	 *
	 * @param array $params
	 * @return array
	 */
	public function createRecurringPaymentsProfile( array $params ) {
		if ( isset( $params['frequency_unit'] ) && $params['frequency_unit'] === 'year' ) {
			$billingPeriod = 'Year';
		} else {
			$billingPeriod = 'Month';
		}
		$frequencyInterval = $params['frequency_interval'] ?? 1;
		$requestParams = [
			'METHOD' => 'CreateRecurringPaymentsProfile',
			// A timestamped token, the value of which was returned in the response to the first call to SetExpressCheckout or SetCustomerBillingAgreement response.
			// Tokens expire after approximately 3 hours.
			'TOKEN' => $params['gateway_session_id'],
			'PROFILESTARTDATE' => gmdate( "Y-m-d\TH:i:s\Z", $params['date'] ), // The date when billing for this profile begins, set it today
			'DESC' => $params['description'],
			'PROFILEREFERENCE' => $params['order_id'],
			'BILLINGPERIOD' => $billingPeriod,
			'BILLINGFREQUENCY' => $frequencyInterval,
			'AMT' => $params['amount'],
			'CURRENCYCODE' => $params['currency'],
			'EMAIL' => $params['email'],
			'AUTOBILLOUTAMT' => 'NoAutoBill', // PayPal does not automatically bill the outstanding balance if payments fail.
			'TOTALBILLINGCYCLES' => 0, // Forever.
			'MAXFAILEDPAYMENTS' => 0, // Just keep trying
		];

		return $this->makeApiCall( $requestParams );
	}

	/**
	 * Doc link: https://developer.paypal.com/api/nvp-soap/get-express-checkout-details-nvp/
	 *
	 * @param string $gatewaySessionId
	 * @return array
	 */
	public function getExpressCheckoutDetails( string $gatewaySessionId ) {
		$requestParams = [
			'METHOD' => 'GetExpressCheckoutDetails',
			'TOKEN' => $gatewaySessionId
		];
		return $this->makeApiCall( $requestParams );
	}

	/**
	 * @param array $params Associative array with a 'subscr_id' key
	 * @return array
	 */
	public function manageRecurringPaymentsProfileStatusCancel( array $params ) {
		$requestParams = [
			'METHOD' => 'ManageRecurringPaymentsProfileStatus',
			'PROFILEID' => $params[ 'subscr_id' ],
			'ACTION' => 'Cancel'
		];
		return $this->makeApiCall( $requestParams );
	}

	/**
	 * Refund paypal
	 * @param array $params
	 *
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function refundPayment( array $params ) : array {
		$requestParams = [
			'METHOD' => 'RefundTransaction',
			'INVOICEID' => $params['order_id'], // optional
			'TRANSACTIONID' => $params[ 'gateway_txn_id' ], // Unique identifier of the transaction to be refunded.
			'REFUNDTYPE' => isset( $params['amount'] ) ? 'Partial' : 'Full'
		];
		if ( isset( $params['amount'] ) ) {
			$requestParams['AMT'] = $params['amount'];
		}
		return $this->makeApiCall( $requestParams );
	}

	/**
	 * Paypal expects auth and version params to be sent within the request body.
	 * https://developer.paypal.com/api/nvp-soap/gs-PayPalAPIs/#link-callpayload
	 *
	 * Note: We're using Certificate Auth and not Signature Auth so that's
	 * why SIGNATURE is missing. I couldn't find an example for Certificate
	 * auth on that page.
	 *
	 * @return array
	 */
	private function getDefaultRequestParams(): array {
		$params['USER'] = $this->user;
		$params['PWD'] = $this->password;
		$params['VERSION'] = $this->version;
		return $params;
	}
}
