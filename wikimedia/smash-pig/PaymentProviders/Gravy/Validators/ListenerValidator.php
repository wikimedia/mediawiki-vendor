<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\Core\ProviderConfiguration;
use SmashPig\PaymentProviders\ValidationException;

class ListenerValidator {
	use ValidatorTrait;

	/**
	 * @throws ValidationException
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
