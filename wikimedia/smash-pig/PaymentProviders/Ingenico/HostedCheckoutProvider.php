<?php

namespace SmashPig\PaymentProviders\Ingenico;

use BadMethodCallException;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentProviders\PaymentDetailResponse;
use SmashPig\PaymentProviders\RiskScorer;

/**
 * Class HostedCheckoutProvider
 *
 * @package SmashPig\PaymentProviders\Ingenico
 */
class HostedCheckoutProvider extends PaymentProvider {
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

		if ( isset( $rawResponse['createdPaymentOutput'] ) ) {
			$this->addPaymentStatusErrorsIfPresent(
				$rawResponse, $rawResponse['createdPaymentOutput']['payment']
			);
		}
		$response = new PaymentDetailResponse();
		$this->prepareResponseObject( $response, $rawResponse );
		$fraudResults = $rawResponse['createdPaymentOutput']['payment']['paymentOutput']['cardPaymentMethodSpecificOutput']['fraudResults'] ?? null;
		if ( $fraudResults ) {
			$response->setRiskScores(
				( new RiskScorer() )->getRiskScores(
					$fraudResults['avsResult'] ?? null,
					$fraudResults['cvvResult'] ?? null
				)
			);
		}
		// Though the response property is plural, its data type is string,
		// and we've only ever seen one token come back at once.
		if ( !empty( $rawResponse['createdPaymentOutput']['tokens'] ) ) {
			$response->setRecurringPaymentToken(
				$rawResponse['createdPaymentOutput']['tokens']
			);
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
