<?php

namespace SmashPig\PaymentProviders\Ingenico;

use BadMethodCallException;
use OutOfBoundsException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Mapper\Mapper;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
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
	 * @return PaymentDetailResponse
	 */
	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse {
		if ( empty( $params['gateway_session_id'] ) ) {
			throw new BadMethodCallException(
				'Called getLatestPaymentStatus with empty gateway_session_id'
			);
		}
		$path = "hostedcheckouts/{$params['gateway_session_id']}";
		$rawResponse = $this->api->makeApiCall( $path, 'GET' );

		$response = new PaymentDetailResponse();
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

			$cardOutput = $paymentOutput['cardPaymentMethodSpecificOutput'];
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
			// Though the 'tokens' response property is plural, its data type is
			// string, and we've only ever seen one token come back at once.
			if ( !empty( $rawResponse['createdPaymentOutput']['tokens'] ) ) {
				$response->setRecurringPaymentToken(
					$rawResponse['createdPaymentOutput']['tokens']
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
		$response = $this->api->makeApiCall( $path, 'POST', $mappedParams );
		$sessionResponse = new CreatePaymentSessionResponse();
		// TODO check $response['invalidTokens'] and map to ValidationErrors
		$sessionResponse->setRawResponse( $response );
		$sessionResponse->setSuccessful( true );
		$sessionResponse->setPaymentSession( $response['hostedCheckoutId'] );
		$sessionResponse->setRedirectUrl(
			$this->getHostedPaymentUrl( $response['partialRedirectUrl'] )
		);
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
	 * @deprecated use getLatestPaymentStatus directly
	 * @param string $hostedPaymentId
	 *
	 * @return PaymentDetailResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getHostedPaymentStatus( string $hostedPaymentId ): PaymentDetailResponse {
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
