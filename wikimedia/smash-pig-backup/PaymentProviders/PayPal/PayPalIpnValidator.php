<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Context;
use SmashPig\Core\Http\EnumValidator;
use SmashPig\Core\Http\OutboundRequest;

class PayPalIpnValidator {

	/**
	 * @param array $post_fields Associative array of fields posted to listener
	 * @return bool
	 */
	public function validate( $post_fields = [] ) {
		$post_fields['cmd'] = '_notify-validate';

		$config = Context::get()->getProviderConfiguration();
		$url = $config->val( 'postback-url' );
		$config->overrideObjectInstance(
			'curl/validator',
			new EnumValidator( [ 'INVALID', 'VERIFIED' ] )
		);
		$request = new OutboundRequest( $url, 'POST' );
		$request->setBody( $post_fields );

		$response = $request->execute();

		if ( $response['body'] === 'VERIFIED' ) {
			return true;
		} elseif ( $response['body'] === 'INVALID' ) {
			return false;
		}

		$responseToJSON = json_encode( $response, JSON_UNESCAPED_UNICODE ) ?: 'Unable to encode response.';
		throw new \UnexpectedValueException(
			'Unexpected $response[\'body\'] received during paypal validate postback-url request: ' . $responseToJSON
		);
	}

}
