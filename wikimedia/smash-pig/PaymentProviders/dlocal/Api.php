<?php

namespace SmashPig\PaymentProviders\dlocal;

use DateTime;
use SmashPig\Core\ApiException;
use SmashPig\Core\Context;
use SmashPig\Core\Helpers\UniqueId;
use SmashPig\Core\Http\OutboundRequest;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\dlocal\ApiMappers\CapturePaymentApiRequestMapper;
use SmashPig\PaymentProviders\dlocal\ApiMappers\DirectCardAuthorizePaymentApiRequestMapper;
use SmashPig\PaymentProviders\dlocal\ApiMappers\HostedPaymentApiRequestMapper;
use SmashPig\PaymentProviders\dlocal\ApiMappers\RecurringChargeCardPaymentApiRequestMapper;
use SmashPig\PaymentProviders\dlocal\ApiMappers\RecurringChargeHostedPaymentApiRequestMapper;
use SmashPig\PaymentProviders\dlocal\ApiMappers\RedirectPaymentApiRequestMapper;
use Symfony\Component\HttpFoundation\Response;

class Api {

	/**
	 * @var string
	 */
	public const INDIA_TIME_ZONE = 'Asia/Calcutta';

	/**
	 * @var string
	 */
	public const PAYMENT_METHOD_ID_CARD = 'CARD';

	/**
	 * @var string
	 */
	public const PAYMENT_METHOD_FLOW_DIRECT = 'DIRECT';

	/**
	 * @var string
	 */
	public const PAYMENT_METHOD_FLOW_REDIRECT = 'REDIRECT';

	/**
	 * @var string
	 */
	public const SUBSCRIPTION_FREQUENCY_UNIT_ONDEMAND = 'ONDEMAND';

	/**
	 * @var string
	 */
	public const SUBSCRIPTION_FREQUENCY_UNIT_MONTHLY = 'MONTH';

	/**
	 * @var string
	 */
	protected $endpoint;

	/**
	 * @var string
	 */
	protected $login;

	/**
	 * @var string
	 */
	protected $trans_key;

	/**
	 * @var string
	 */
	protected $secret;

	/**
	 * dLocal API Version.
	 *
	 * 2.1 is the current version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * @var SignatureCalculator
	 */
	private $signatureCalculator;

	/**
	 * Should be set to one of the two SUBSCRIPTION_FREQUENCY_UNIT_* values
	 * 'ONDEMAND' has fewer limitations for prenotify compared with 'MONTH'
	 * (allows sending a retry in the same month, since needs 2 days to process).
	 * HostedPaymentApiRequestMapper uses ONDEMAND by default if not set.
	 * @var string|null
	 */
	protected $upiSubscriptionFrequency;

	private $additionalApiParams = [];

	public function __construct( array $params ) {
		$this->endpoint = $params['endpoint'];
		$returnUrl = $params['callback_url'] ?? null;
		$notificationUrl = $params['notification_url'] ?? null;
		$this->login = $params['login'];
		$this->trans_key = $params['trans-key'];
		$this->secret = $params['secret'];
		$this->version = $params['version'];
		if ( !empty( $params['upi_subscription_frequency'] ) ) {
			$this->upiSubscriptionFrequency = $params['upi_subscription_frequency'];
		}
		$this->signatureCalculator = Context::get()->getProviderConfiguration()->object( 'signature-calculator' );
		if ( $returnUrl ) {
			$this->additionalApiParams['return_url'] = $returnUrl;
		}
		if ( $notificationUrl ) {
			$this->additionalApiParams['notification_url'] = $notificationUrl;
		}
	}

	/**
	 * @param string $method
	 * @param string $route
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function makeApiCall( string $method = 'POST', string $route = '', array $params = [] ): array {
		$request = $this->createRequestBasedOnMethodAndSetBody( $method, $route, $params );

		$this->setRequestHeaders( $request );
		$rawResponse = $request->execute();
		Logger::debug( 'Raw response from dlocal: ' . $rawResponse['body'] );

		if ( $this->responseHasErrorStatusCode( $rawResponse ) ) {
			throw new ApiException(
				'Response Error(' . $rawResponse['status'] . ') ' . $rawResponse['body']
			);
		}

		return json_decode( $rawResponse['body'], true );
	}

	public function getPaymentMethods( string $country ): array {
		$params = [
			'country' => $country,
		];

		return $this->makeApiCall( 'GET', 'payments-methods', $params );
	}

	/**
	 * @param array $apiParams
	 * @return array
	 * @throws ApiException
	 */
	public function makePaymentApiCall( array $params, IAPIRequestMapper $mapper ): array {
		$params = array_merge( $this->additionalApiParams, $params );
		$mapper->setInputParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $mapper->getAll() );
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function cardAuthorizePayment( array $params ): array {
		return $this->makePaymentApiCall( $params, new DirectCardAuthorizePaymentApiRequestMapper() );
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function redirectPayment( array $params ): array {
		return $this->makePaymentApiCall( $params, new RedirectPaymentApiRequestMapper() );
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function redirectHostedPayment( array $params ): array {
		if ( self::isRecurringUpi( $params ) ) {
			$params['upi_subscription_frequency'] = $this->upiSubscriptionFrequency;
		}
		return $this->makePaymentApiCall( $params, new HostedPaymentApiRequestMapper() );
	}

	public static function isRecurringUpi( array $params ): bool {
		return ( $params['recurring'] ?? false ) &&
			( $params['payment_submethod'] ?? '' ) === 'upi';
	}

	/**
	 * 24 hours before the payment is due, must send pre-debit notification (prenotification).
	 * After the prenotification is approved by the issuer, the user will be notified that they will be charged
	 * the amount specified in the request. 48 hours after the prenotification is approved, dLocal will automatically
	 * trigger the charge.
	 *
	 * @param array $params
	 *
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPaymentFromToken( array $params ): array {
		// todo: needs to send the prenotice 48 hrs ahead, and able to try another time if not success with PRENOTIFY false
		return $this->makePaymentApiCall( $params, new RecurringChargeHostedPaymentApiRequestMapper() );
	}

	/**
	 * @param string $gatewayTxnId
	 * can be used for get bt wallet token
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentDetail( string $gatewayTxnId ): array {
		$route = 'payments/' . $gatewayTxnId;
		return $this->makeApiCall( 'GET', $route );
	}

	/**
	 * Capture authorized payment.
	 *
	 * $params['gateway_txn_id'] is required
	 *
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function capturePayment( array $params ): array {
		return $this->makePaymentApiCall( $params, new CapturePaymentApiRequestMapper() );
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function makeRecurringCardPayment( array $params ): array {
		return $this->makePaymentApiCall( $params, new RecurringChargeCardPaymentApiRequestMapper() );
	}

	/**
	 * Get payment status.
	 *
	 * https://docs.dlocal.com/reference/retrieve-a-payment-status
	 *
	 * @param string $gatewayTxnId
	 * @return array
	 * @throws ApiException
	 */
	public function getPaymentStatus( string $gatewayTxnId ): array {
		$route = 'payments/' . $gatewayTxnId . '/status';
		return $this->makeApiCall( 'GET', $route );
	}

	/**
	 * @param string $gatewayTxnId
	 *
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelPayment( string $gatewayTxnId ): array {
		$route = 'payments/' . $gatewayTxnId . '/cancel';
		return $this->makeApiCall( 'POST', $route );
	}

	/**
	 * Set dLocal request headers
	 * https://docs.dlocal.com/reference/payins-security#headers
	 *
	 * @param OutboundRequest $request
	 * @return void
	 */
	protected function setRequestHeaders( OutboundRequest $request ): void {
		// date format is ISO8601 compatible
		$date = ( new DateTime() )->format( 'Y-m-d\TH:i:s.v\Z' );

		// set the simple headers
		$request->setHeader( 'X-Date', $date )
			->setHeader( 'X-Login', $this->login )
			->setHeader( 'X-Trans-Key', $this->trans_key )
			->setHeader( 'Content-Type', 'application/json' )
			->setHeader( 'X-Version', $this->version )
			->setHeader( 'User-Agent', 'SmashPig' )
			->setHeader( 'X-Idempotency-Key', UniqueId::generate() );

		// calculate the request signature and add to 'Authorization' header
		// as instructed in https://docs.dlocal.com/reference/payins-security#headers
		$requestBody = ( $request->getMethod() === 'POST' ) ? $request->getBody() : '';
		$signatureInput = $this->login . $date . $requestBody;
		$signature = $this->signatureCalculator->calculate( $signatureInput, $this->secret );
		// dLocal signatures have a text prefix which needs to be in the header
		$signaturePrefix = 'V2-HMAC-SHA256, Signature: ';
		$request->setHeader( 'Authorization', $signaturePrefix . $signature );
	}

	/**
	 * The OutboundRequest will be created differently depending on the $method(HTTP verb e.g GET/POST/PUT).
	 * In this function, we detect the verb and create the appropriate form of OutboundRequest.
	 *
	 * The $body of the request is also different between methods, for GET we set the $body to null and for
	 * POST, we json_encode() the $body.
	 *
	 *
	 * @param string $method
	 * @param array $params
	 * @return OutboundRequest
	 */
	protected function createRequestBasedOnMethodAndSetBody( string $method, string $route, array $params ): OutboundRequest {
		$apiUrl = empty( $route ) ? $this->endpoint : $this->endpoint . '/' . $route;

		if ( $method === 'GET' ) {
			if ( $params !== [] ) {
				$apiUrl .= '?' . http_build_query( $params );
			}

			$body = null;
		} else {
			$body = json_encode( $params );
		}

		$request = new OutboundRequest( $apiUrl, $method );
		$request->setBody( $body );
		return $request;
	}

	/**
	 * dLocal uses non-200 HTTP headers to indicate response errors
	 *
	 * @see https://docs.dlocal.com/reference/http-errors-payments
	 * @param array $rawResponse
	 * @return bool
	 */
	protected function responseHasErrorStatusCode( array $rawResponse ): bool {
		return $rawResponse['status'] !== Response::HTTP_OK;
	}
}
