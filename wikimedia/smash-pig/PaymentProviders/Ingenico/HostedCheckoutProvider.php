<?php

namespace SmashPig\PaymentProviders\Ingenico;

use BadMethodCallException;
use Exception;
use OutOfBoundsException;
use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Mapper\Mapper;
use SmashPig\Core\PaymentError;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\RiskScorer;

/**
 * Class HostedCheckoutProvider
 *
 * @package SmashPig\PaymentProviders\Ingenico
 */
class HostedCheckoutProvider extends PaymentProvider implements IGetLatestPaymentStatusProvider {
	/**
	 * @var string subdomain
	 */
	protected $subdomain;

	/**
	 * HostedCheckoutProvider constructor.
	 * @param array $options
	 * @throws SmashPigException
	 */
	public function __construct( array $options = [] ) {
		parent::__construct( $options );
		if ( array_key_exists( 'subdomain', $options ) ) {
			$this->subdomain = $options['subdomain'];
		} else {
			throw new SmashPigException( "Subdomain key missing from configuration." );
		}
	}

	/**
	 * Get the latest status from Ingenico
	 *
	 * $params['gateway_session_id'] should match the hostedPaymentId
	 *
	 * @param array $params
	 * @return PaymentProviderExtendedResponse
	 */
	public function getLatestPaymentStatus( array $params ): PaymentProviderExtendedResponse {
		if ( empty( $params['gateway_session_id'] ) ) {
			throw new BadMethodCallException(
				'Called getLatestPaymentStatus with empty gateway_session_id'
			);
		}
		$path = "hostedcheckouts/{$params['gateway_session_id']}";
		$response = new PaymentProviderExtendedResponse();
		$rawResponse = $this->makeApiCallAndSetBasicResponseProperties( $response, $path );
		if ( $rawResponse === null ) {
			// Just return the failed PaymentProviderExtendedResponse with the NO_RESPONSE error
			return $response;
		}

		// When the donor has entered card details, we get a createdPaymentOutput array
		// in the hostedcheckouts GET response.
		$paymentCreated = isset( $rawResponse['createdPaymentOutput'] );
		if ( $paymentCreated ) {
			// This copies any errors from a deeply nested property to the
			// root node, where they can be picked up by subsequent code.
			$this->addPaymentStatusErrorsIfPresent(
				$rawResponse,
				$rawResponse['createdPaymentOutput']['payment']
			);
		}
		// Always call prepareResponseObject to set the rawResponse
		// and map any root-node errors. When a payment has been created
		// this method will also set the rawStatus and mapped status
		// property.
		$this->prepareResponseObject( $response, $rawResponse );
		if ( $paymentCreated ) {
			$paymentOutput = $rawResponse['createdPaymentOutput']['payment']['paymentOutput'];
			// Returned amount is in 'cents', multiplied by 100
			// even if the currency has no minor unit
			$response->setAmount( $paymentOutput['amountOfMoney']['amount'] / 100 );
			$response->setCurrency( $paymentOutput['amountOfMoney']['currencyCode'] );

			if ( !empty( $paymentOutput['cardPaymentMethodSpecificOutput'] ) ) {
				$this->mapCardSpecificStatusProperties(
					$response, $paymentOutput['cardPaymentMethodSpecificOutput']
				);
			}
			// Though the 'tokens' response property is plural, its data type is
			// string, and we've only ever seen one token come back at once.
			if ( !empty( $rawResponse['createdPaymentOutput']['tokens'] ) ) {
				$response->setRecurringPaymentToken(
					$rawResponse['createdPaymentOutput']['tokens']
				);
			}
		} elseif ( isset( $rawResponse['status'] ) ) {
			// If no payment has been created, the GET response only
			// has a single status property - {"status": "IN_PROGRESS"}
			$response->setRawStatus( $rawResponse['status'] );
			$response->setStatus(
				( new HostedCheckoutStatus() )->normalizeStatus( $rawResponse['status'] )
			);
			$response->setSuccessful( false );
		}

		return $response;
	}

	protected function mapCardSpecificStatusProperties(
		PaymentProviderExtendedResponse $response,
		array $cardOutput
	) {
		try {
			$decoded = ReferenceData::decodePaymentMethod( $cardOutput['paymentProductId'] );
			$response->setPaymentSubmethod( $decoded['payment_submethod'] );
		} catch ( OutOfBoundsException $ex ) {
			Logger::warning( $ex->getMessage() );
		}

		// Fraud results and tokens only come back when a payment has been created
		$fraudResults = $cardOutput['fraudResults'] ?? null;
		if ( $fraudResults ) {
			$response->setRiskScores(
				( new RiskScorer() )->getRiskScores(
					$fraudResults['avsResult'] ?? null,
					$fraudResults['cvvResult'] ?? null
				)
			);
		}

		if ( !empty( $cardOutput['card']['cardholderName'] ) ) {
			$donorDetails = new DonorDetails();
			$donorDetails->setFullName( $cardOutput['card']['cardholderName'] );
			$response->setDonorDetails( $donorDetails );
		}
		$response->setInitialSchemeTransactionId(
			$cardOutput['initialSchemeTransactionId'] ??
			// Worldline docs say to "Use this value in case the initialSchemeTransactionId property is empty."
			$cardOutput['schemeTransactionId'] ?? null
		);
	}

	/**
	 * @deprecated use createPaymentSession instead
	 * @param array $params
	 *
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createHostedPayment( array $params ): array {
		if ( empty( $params ) ) {
			throw new BadMethodCallException(
				'Called createHostedPayment with empty parameters'
			);
		}
		$path = 'hostedcheckouts';
		$response = $this->api->makeApiCall( $path, 'POST', $params );
		return $response;
	}

	public function createPaymentSession( array $params ): CreatePaymentSessionResponse {
		// https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/hostedcheckouts/create.html
		$mappedParams = $this->mapCreatePaymentSessionParams( $params );
		$path = 'hostedcheckouts';
		$sessionResponse = new CreatePaymentSessionResponse();
		$rawResponse = $this->makeApiCallAndSetBasicResponseProperties( $sessionResponse, $path, 'POST', $mappedParams );
		if ( $rawResponse ) {
			// TODO check $rawResponse['invalidTokens'] and map to ValidationErrors
			$sessionResponse->setSuccessful( true );
			$sessionResponse->setPaymentSession( $rawResponse['hostedCheckoutId'] );
			$sessionResponse->setRedirectUrl(
				$this->getHostedPaymentUrl( $rawResponse['partialRedirectUrl'] )
			);
		}
		return $sessionResponse;
	}

	protected function mapCreatePaymentSessionParams( $params ) {
		$mapConfig = $this->providerConfiguration->val( 'maps/create-payment-session' );
		return Mapper::map(
			$params,
			$mapConfig['path'],
			$mapConfig['transformers'],
			null,
			true
		);
	}

	/**
	 * Makes an API call, setting rawResponse on success and translating exceptions to
	 * PaymentErrors on failure. Returns the raw response array if successful.
	 *
	 * @param PaymentProviderResponse $responseObject
	 * @param string $path
	 * @param string $method
	 * @param array|null $params
	 * @return array|null
	 */
	protected function makeApiCallAndSetBasicResponseProperties(
		PaymentProviderResponse $responseObject, string $path, string $method = 'GET', ?array $params = null
	): ?array {
		try {
			$rawResponse = $this->api->makeApiCall( $path, $method, $params );
			$responseObject->setRawResponse( $rawResponse );
			return $rawResponse;
		} catch ( Exception $ex ) {
			$responseObject->addErrors( new PaymentError(
				ErrorCode::NO_RESPONSE, $ex->getMessage(), LogLevel::ERROR
			) );
			$responseObject->setSuccessful( false );
			return null;
		}
	}

	/**
	 * @param string $hostedPaymentId
	 *
	 * @return PaymentProviderExtendedResponse
	 * @throws \SmashPig\Core\ApiException
	 * @deprecated use getLatestPaymentStatus directly
	 */
	public function getHostedPaymentStatus( string $hostedPaymentId ): PaymentProviderExtendedResponse {
		return $this->getLatestPaymentStatus( [ 'gateway_session_id' => $hostedPaymentId ] );
	}

	/**
	 * @param string $partialRedirectUrl
	 *
	 * @return string
	 */
	public function getHostedPaymentUrl( string $partialRedirectUrl ): string {
		return "https://{$this->subdomain}.$partialRedirectUrl";
	}
}
