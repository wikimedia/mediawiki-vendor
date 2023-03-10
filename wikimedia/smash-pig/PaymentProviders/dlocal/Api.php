<?php

namespace SmashPig\PaymentProviders\dlocal;

use DateTime;
use DateTimeZone;
use SmashPig\Core\ApiException;
use SmashPig\Core\Context;
use SmashPig\Core\Helpers\UniqueId;
use SmashPig\Core\Http\OutboundRequest;
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
	public const SUBSCRIPTION_FREQUENCY_UNIT = 'ONDEMAND';

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
	 * @var mixed
	 */
	protected $callback_url;
	/**
	 * @var mixed
	 */
	protected $notification_url;

	/**
	 * @var SignatureCalculator
	 */
	private $signatureCalculator;

	public function __construct( array $params ) {
		$this->endpoint = $params['endpoint'];
		$this->callback_url = $params['callback_url'];
		$this->notification_url = $params['notification_url'];
		$this->login = $params['login'];
		$this->trans_key = $params['trans-key'];
		$this->secret = $params['secret'];
		$this->version = $params['version'];
		$this->signatureCalculator = Context::get()->getProviderConfiguration()->object( 'signature-calculator' );
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
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function authorizePayment( array $params ): array {
		$apiParams = $this->mapParamsToCardAuthorizePaymentRequestParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function redirectPayment( array $params ): array {
		$apiParams = $this->mapParamsToAuthorizePaymentRequestParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
	}

	/**
	 * 48 hours before the payment is due, must send pre-debit notification (prenotification).
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
		$apiParams = $this->getCreatePaymentFromTokenParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
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
		$apiParams = $this->mapParamsToCapturePaymentRequestParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws ApiException
	 */
	public function makeRecurringPayment( array $params ): array {
		$apiParams = $this->mapParamsToCardRecurringPaymentRequestParams( $params );
		return $this->makeApiCall( 'POST', 'payments', $apiParams );
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
		$date = ( new \DateTime() )->format( 'Y-m-d\TH:i:s.v\Z' );

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

	/**
	 * @param array $params
	 * @return array
	 */
	protected function mapParamsToCapturePaymentRequestParams( array $params ): array {
		$apiParams = [];
		if ( array_key_exists( 'gateway_txn_id', $params ) ) {
			$apiParams['authorization_id'] = $params['gateway_txn_id'];
		} else {
			throw new \InvalidArgumentException( "gateway_txn_id is a required field" );
		}
		if ( array_key_exists( 'amount', $params ) ) {
			$apiParams['amount'] = $params['amount'];
		}
		if ( array_key_exists( 'currency', $params ) ) {
			$apiParams['currency'] = $params['currency'];
		}
		if ( array_key_exists( 'order_id', $params ) ) {
			$apiParams['order_id'] = $params['order_id'];
		}
		return $apiParams;
	}

	protected function fillNestedArrayFields( array $sourceArray, array &$destinationArray, array $fieldParams ): void {
		$array = [];
		foreach ( $fieldParams as $field => $apiParams ) {
			foreach ( $apiParams as $key => $value ) {
				if ( array_key_exists( $value, $sourceArray ) ) {
					$array[$field][$key] = $sourceArray[$value];
				}
			}
			if ( array_key_exists( $field, $destinationArray ) ) {
				$destinationArray[$field] = array_merge( $destinationArray[$field], $array[$field] );
			} elseif ( count( $array[$field] ) > 0 ) {
				$destinationArray[$field] = $array[$field];
			}
		}
	}

	protected function mapParamsToAuthorizePaymentRequestParams( array $params ): array {
		$apiParams = [
			'amount' => $params['amount'],
			'currency' => $params['currency'],
			'country' => $params['country'],
			'order_id' => $params['order_id'],
			'payment_method_flow' => self::PAYMENT_METHOD_FLOW_REDIRECT, // Set as a default and may be overridden in unique situations.
			'payer' => [
				'name' => $params['first_name'] . ' ' . $params['last_name'],
				'email' => $params['email'],
			]
		];

		if ( $this->callback_url !== null ) {
			$apiParams['callback_url'] = $this->callback_url;
		}
		if ( $this->notification_url !== null ) {
			$apiParams['notification_url'] = $this->notification_url;
		}

		if ( array_key_exists( 'payment_method_id', $params ) ) {
			$apiParams['payment_method_id'] = $params['payment_method_id'];
		}

		if ( array_key_exists( 'description', $params ) ) {
			$apiParams['description'] = $params['description'];
		}

		if ( array_key_exists( 'return_url', $params ) ) {
			$apiParams['callback_url'] = $params['return_url'];
		}
		$country = $params['country'] ?? '';
		$apiFields = [];

		$apiFields['payer'] = [
			'document' => 'fiscal_number',
			'user_reference' => 'contact_id',
			'ip' => 'user_ip',
		];
		if ( $country !== 'IN' ) {
			$apiFields['address'] = [
				'state' => 'state_province',
				'city' => 'city',
				'zip_code' => 'postal_code',
				'street' => 'street_address',
				'number' => 'street_number',
			];
		} else {
			// use Mumbai as default city for all india pmt, can remove the address field from frontend
			$apiParams['payer']['address'] = [
				'city' => 'Mumbai',
			];
			$isRecurring = $params['recurring'] ?? '';
			// if recurring, needs to create a monthly subscription with in time zone
			if ( $isRecurring ) {
				$this->mapUPIRecurringParam( $apiParams );
			}
		}

		$this->fillNestedArrayFields( $params, $apiParams, $apiFields );
		return $apiParams;
	}

	/**
	 * @param array &$apiParams
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function mapUPIRecurringParam( array &$apiParams ): void {
		$apiParams["payment_method_id"] = "IR"; // in india, only IR support recurring
		$date = new DateTime( 'now', new DateTimeZone( self::INDIA_TIME_ZONE ) );
		$apiParams["wallet"] = [
			"save" => true,
			"capture" => true,
			"verify" => false,
			"username" => $apiParams['payer']['name'],
			"email" => $apiParams['payer']['email'],
			"recurring_info" => [
				// "ONDEMAND" has less limitation for prenotify compare with "MONTH"
				// ( allow recharge send on the same month, since needs 2 days to process),
				// while we need to add a text for client to indicate this is only monthly
				"subscription_frequency_unit" => self::SUBSCRIPTION_FREQUENCY_UNIT,
				"subscription_frequency" => 1,
				"subscription_start_at" => $date->format( 'Ymd' ),
				"subscription_end_at" => "20991231" // if more than year 2100, dlocal reject txn so use 20991231
			]
		];
	}

	/**
	 * @param array $params
	 * Below are for IR but could be the same for other recurring, will see
	 * @return array
	 */
	protected function getCreatePaymentFromTokenParams( array $params ): array {
		$apiParams = [
			'amount' => $params['amount'],
			'currency' => $params['currency'],
			'country' => $params['country'],
			'payment_method_id' => $params['payment_method_id'],
			'payment_method_flow' => self::PAYMENT_METHOD_FLOW_DIRECT, // recurring charge just use direct
			'payer' => [
				'name' => $params['first_name'] . ' ' . $params['last_name'],
				'email' => $params['email'],
				'document' => $params['fiscal_number'],
			],
			'wallet' => [
				'token' => $params['recurring_payment_token'],
				'recurring_info' => [
					'prenotify' => true,
				],
			],
			'description' => 'charge recurring',
			'order_id' => $params['order_id'],
			'notification_url' => $params['notification_url'],
		];

		if ( array_key_exists( 'description', $params ) ) {
			$apiParams['description'] = $params['description'];
		}

		return $apiParams;
	}

	/**
	 * @param array $params
	 * Convert the API request body to DLocal Authorize Payment Request standards
	 * @return array
	 */
	protected function mapParamsToCardAuthorizePaymentRequestParams( array $params ): array {
		$apiParams = $this->mapParamsToAuthorizePaymentRequestParams( $params );

		$apiParams = $this->check3DSecure( $params, $apiParams );

		$isRecurring = $params['recurring'] ?? false;
		$paramsHasPaymentToken = array_key_exists( 'payment_token', $params );

		// Ensure this transaction is not a redirect payment attempt by checking for the presence of
		// a payment token.

		if ( $paramsHasPaymentToken ) {
			$apiParams['payment_method_id'] = self::PAYMENT_METHOD_ID_CARD;
			$apiParams['payment_method_flow'] = self::PAYMENT_METHOD_FLOW_DIRECT;
			$apiParams['card'] = [
				'token' => $params['payment_token'],
			];
			if ( $isRecurring ) {
				$apiParams['card']['save'] = true;
			}
			$apiParams['card']['capture'] = false;
		}

		return $apiParams;
	}

	protected function mapParamsToCardRecurringPaymentRequestParams( array $params ): array {
		$apiParams = $this->mapParamsToAuthorizePaymentRequestParams( $params );
		$apiParams['payment_method_id'] = self::PAYMENT_METHOD_ID_CARD;
		$apiParams['payment_method_flow'] = self::PAYMENT_METHOD_FLOW_DIRECT;

		$apiParams['card'] = [
			'card_id' => $params['recurring_payment_token']
		];

		$apiParams['card']['capture'] = true;

		return $apiParams;
	}

	/**
	 * @param array $params
	 * @param array $apiParams
	 * @return array
	 */
	protected function check3DSecure( array $params, array $apiParams ): array {
		if ( array_key_exists( 'use_3d_secure', $params ) && $params['use_3d_secure'] === true ) {
			$apiParams['three_dsecure'] = [
				'force' => true,
			];
		}
		return $apiParams;
	}

}
