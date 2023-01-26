<?php

namespace SmashPig\PaymentProviders\Ingenico;

use BadMethodCallException;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
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
		return $this->getHostedPaymentStatus( $params['gateway_session_id'] );
	}

	/**
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

	/**
	 * @param string $hostedPaymentId
	 *
	 * @return PaymentDetailResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getHostedPaymentStatus( string $hostedPaymentId ): PaymentDetailResponse {
		if ( !$hostedPaymentId ) {
			throw new BadMethodCallException(
				'Called getHostedPaymentStatus with empty hostedPaymentId'
			);
		}
		$path = "hostedcheckouts/$hostedPaymentId";
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
			$cardOutput = $rawResponse['createdPaymentOutput']['payment']['paymentOutput']['cardPaymentMethodSpecificOutput'];
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
	 * @param string $partialRedirectUrl
	 *
	 * @return string
	 */
	public function getHostedPaymentUrl( string $partialRedirectUrl ): string {
		return "https://{$this->subdomain}.$partialRedirectUrl";
	}
}
