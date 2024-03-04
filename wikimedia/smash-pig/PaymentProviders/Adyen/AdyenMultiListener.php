<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Context;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;

class AdyenMultiListener implements IHttpActionHandler {

	public function execute( Request $request, Response $response ) {
		$inputStream = fopen( 'php://input', 'r' );
		$firstChar = fgetc( $inputStream );
		fclose( $inputStream );
		$config = Context::get()->getProviderConfiguration();
		if ( $firstChar == '<' ) {
			$listener = $config->object( 'soap-listener' );
		} else {
			$listener = $config->object( 'rest-listener' );
		}
		$listener->execute( $request, $response );
	}
}
