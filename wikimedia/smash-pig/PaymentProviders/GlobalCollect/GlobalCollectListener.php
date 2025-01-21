<?php namespace SmashPig\PaymentProviders\GlobalCollect;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\GlobalCollect\ExpatriatedMessages\PaymentMessage;

/**
 * Dispatches incoming messages accoring to type
 */
class GlobalCollectListener extends RestListener {
	protected $success = false;

	protected function parseEnvelope( Request $request ) {
		$message = new PaymentMessage();
		$message->constructFromValues( $request->getValues() );

		$this->success = true;

		return [ $message ];
	}

	/**
	 * Stub-- maybe this is an egregious pure virtual function
	 */
	protected function ackMessage( ListenerMessage $msg ) {
		return true;
	}

	protected function ackEnvelope() {
		if ( $this->success ) {
			$this->response->setContent( "OK\n" );
		} else {
			$this->response->setContent( "NOK\n" );
		}
	}

	/**
	 * Stub -- should be implemented using SSL client cert
	 */
	protected function doMessageSecurity( ListenerMessage $msg ) {
		return true;
	}
}
