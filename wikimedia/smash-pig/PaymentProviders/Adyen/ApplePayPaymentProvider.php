<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class ApplePayPaymentProvider extends PaymentProvider {

	/**
	 * @var array
	 */
	private $sessionDomains;
	private $merchantIdentifier;
	private $displayName;
	private $domainName;
	private $certificatePath;
	private $certificatePassword;

	public function __construct( array $options ) {
		parent::__construct( $options );
		$this->merchantIdentifier = $options['merchant-identifier'] ?? null;
		$this->displayName = $options['display-name'] ?? null;
		$this->domainName = $options['domain-name'] ?? null;
		$this->certificatePath = $options['certificate-path'] ?? null;
		$this->certificatePassword = $options['certificate-password'] ?? null;
		$this->sessionDomains = $options['session-domains'] ?? [];
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		if ( !empty( $params['recurring_payment_token'] ) && !empty( $params['processor_contact_id'] ) ) {
			return $this->createRecurringPaymentWithShopperReference( $params );
		}
		$rawResponse = $this->api->createApplePayPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		$this->mapStatus(
			$response,
			$rawResponse,
			new ApprovalNeededCreatePaymentStatus(),
			$rawResponse['resultCode'] ?? null
		);
		$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );
		// additionalData has the recurring details
		if ( isset( $rawResponse['additionalData'] ) ) {
			$this->mapAdditionalData( $rawResponse['additionalData'], $response );
		}

		return $response;
	}

	/**
	 * This method should never be called, since Apple Pay does not include any flow in
	 * which the user is redirected to an external site and then returns to ours. (That's
	 * where a payment details status normalizer would be used.)
	 *
	 * {@inheritDoc}
	 * @see \SmashPig\PaymentProviders\Adyen\PaymentProvider::getPaymentDetailsStatusNormalizer()
	 */
	protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer {
		throw new \BadMethodCallException( 'No payment details status normalizer for Apple Pay.' );
	}

	protected function getPaymentDetailsSuccessfulStatuses(): array {
		throw new \BadMethodCallException( 'Unexpected getPaymentDetails call for Apple Pay.' );
	}

	public function createPaymentSession( array $params ): array {
		$params += [
			'merchant_identifier' => $this->merchantIdentifier,
			'display_name' => $this->displayName,
			'domain_name' => $this->domainName,
			'certificate_path' => $this->certificatePath,
			'certificate_password' => $this->certificatePassword
		];
		$this->validateParameters( $params );

		// We don't wrap this in any standardized Response object.
		// Partly out of laziness, but partly because Apple wants
		// the entire session array to be returned verbatim to the
		// onValidateMerchant completion function.
		return $this->api->createApplePaySession( $params );
	}

	protected function validateParameters( array $params ) {
		if (
			empty( $params['merchant_identifier'] ) ||
			empty( $params['display_name'] ) ||
			empty( $params['domain_name'] ) ||
			empty( $params['certificate_path'] ) ||
			empty( $params['validation_url'] )
			// It's possible that certificate_password can be blank
		) {
			throw new \RuntimeException(
				'Creating an Apple Pay session requires a merchant identifier, display ' .
				'name, domain name, validation url and certificate path to be provided ' .
				'either in config (the constructor-parameters under payment-provider/' .
				'apple) or in the $params argument to createPaymentSession.'
			);
		}
		$validationDomain = parse_url( $params['validation_url'], PHP_URL_HOST );
		if ( !in_array( $validationDomain, $this->sessionDomains ) ) {
			throw new \UnexpectedValueException(
				"Shenanigans! validation_url {$params['validation_url']} is not on an " .
				'allowed domain, or domains are missing under configuration key ' .
				'payment-provider/apple/constructor-parameters/session-domains.'
			);
		}
	}
}
