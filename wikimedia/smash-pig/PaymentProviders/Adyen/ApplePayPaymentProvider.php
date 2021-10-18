<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class ApplePayPaymentProvider extends PaymentProvider {

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
	}

	public function createPayment( array $params ) : CreatePaymentResponse {
		$rawResponse = $this->api->createApplePayPayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		$this->mapStatus(
			$response,
			$rawResponse,
			new CreatePaymentStatus(),
			$rawResponse['resultCode'] ?? null
		);
		$this->mapRestIdAndErrors( $response, $rawResponse );
		// additionalData has the recurring details
		if ( isset( $rawResponse['additionalData'] ) ) {
			$this->mapAdditionalData( $rawResponse['additionalData'], $response );
		}

		return $response;
	}

	protected function getPaymentDetailsStatusNormalizer() : StatusNormalizer {
		return new CreatePaymentStatus();
	}

	public function createPaymentSession( array $params ) : array {
		$params += [
			'merchant_identifier' => $this->merchantIdentifier,
			'display_name' => $this->displayName,
			'domain_name' => $this->domainName,
			'certificate_path' => $this->certificatePath,
			'certificate_password' => $this->certificatePassword
		];
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

		// We don't wrap this in any standardized Response object.
		// Partly out of laziness, but partly because Apple wants
		// the entire session array to be returned verbatim to the
		// onValidateMerchant completion function.
		return $this->api->createApplePaySession( $params );
	}
}
