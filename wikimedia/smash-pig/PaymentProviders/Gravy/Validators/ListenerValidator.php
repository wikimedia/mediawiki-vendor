<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\Core\ProviderConfiguration;
use SmashPig\PaymentProviders\ValidationException;

/**
 * This class contains validator logic used in the webhook listener class.
 */
class ListenerValidator {
	use ValidatorTrait;

	/**
	 * Validates the authorization params in the webhook event header
	 *
	 * @param array $params
	 * @param ProviderConfiguration $config
	 * @throws ValidationException
	 * @return void
	 */
	public function validateWebhookEventHeader( array $params, ProviderConfiguration $config ): void {
		$required = [
			'AUTHORIZATION'
		];

		$this->validateFields( $required, $params );

		// Gr4vy currently only supports basic authentication for webhook security
		$base64_authorization_value = 'Basic ' . base64_encode( $config->val( 'accounts/webhook/username' ) . ':' . $config->val( 'accounts/webhook/password' ) );

		if ( $params['AUTHORIZATION'] != $base64_authorization_value ) {
			throw new ValidationException( 'Invalid Authorisation header', [
				'AUTHORISATION' => 'invalid'
			] );
		}
	}
}
