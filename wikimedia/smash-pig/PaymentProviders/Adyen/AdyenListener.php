<?php namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\SoapListener;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

class AdyenListener extends SoapListener {

	protected $wsdlpath = "https://ca-live.adyen.com/ca/services/Notification?wsdl";

	protected $classmap = [
		'NotificationRequest'      => 'SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequest',
		'NotificationRequestItem'  => 'SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem',
		'anyType2anyTypeMap'       => 'SmashPig\PaymentProviders\Adyen\WSDL\anyType2anyTypeMap',
		'entry'                    => 'SmashPig\PaymentProviders\Adyen\WSDL\entry',
		'sendNotification'         => 'SmashPig\PaymentProviders\Adyen\WSDL\sendNotification',
		'sendNotificationResponse' => 'SmashPig\PaymentProviders\Adyen\WSDL\sendNotificationResponse',
		'Amount'                   => 'SmashPig\PaymentProviders\Adyen\WSDL\Amount',
		'ServiceException'         => 'SmashPig\PaymentProviders\Adyen\WSDL\ServiceException',
		'Error'                    => 'SmashPig\PaymentProviders\Adyen\WSDL\Error',
		'Type'                     => 'SmashPig\PaymentProviders\Adyen\WSDL\Type',
	];

	public function __construct() {
		require_once 'WSDL/Notification.php';
		parent::__construct();
	}

	/**
	 * Run any gateway/Message specific security.
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 *
	 * @throws ListenerSecurityException on security violation
	 */
	protected function doMessageSecurity( ListenerMessage $msg ) {
		// I have no specific message security at this time
		return true;
	}

	/**
	 * Positive acknowledgement of successful Message processing all the way through the chain.
	 *
	 * In the case of Adyen -- error handling happened far up the stack so if we've made it
	 * here we're golden and we should just let the message pass through unhindered.
	 *
	 * @param ListenerMessage $msg that was processed.
	 */
	protected function ackMessage( ListenerMessage $msg ) {
		return true;
	}

	/** === WSDL Handling Methods === */

	/**
	 * @param WSDL\sendNotification $var
	 *
	 * @return WSDL\sendNotificationResponse
	 */
	public function sendNotification( WSDL\sendNotification $var ) {
		$messages = [];

		$respstring = "[failed]";

		if ( $var->notification instanceof WSDL\NotificationRequest ) {
			if ( $var->notification->live ) {
				Logger::info( "Notification received from live server." );
			} else {
				Logger::info( "Notification received from test server." );
			}

			// Create Messages from the hideous SOAPy mess
			if ( is_array( $var->notification->notificationItems->NotificationRequestItem ) ) {
				foreach ( $var->notification->notificationItems->NotificationRequestItem as $item ) {
					$obj = $this->createAdyenMsgObjFromItem( $item );
					if ( $obj !== false ) {
						$messages[ ] = $obj;
					}
				}
			} else {
				$obj = $this->createAdyenMsgObjFromItem(
					$var->notification->notificationItems->NotificationRequestItem
				);
				if ( $obj !== false ) {
					$messages[ ] = $obj;
				}
			}

			$numItems = count( $messages );
			Logger::info( "Extracted $numItems from received message. Beginning processing loop." );

			// Now process each message to the best of our ability
			foreach ( $messages as $msg ) {
				if ( $this->processMessage( $msg ) ) {
					Logger::debug( "Message successfully processed. Moving along..." );
				} else {
					Logger::error( "Message was not successfully processed!", $msg );
				}
			}

			Logger::info( 'Finished processing of IPN message, retuning accepted.' );
			$respstring = '[accepted]';

		} else {
			Logger::warning( "Received notification is not instance of NotificationRequest!", $var );
			$this->server->fault( 500, 'Received notification is not instance of NotificationRequest!' );
		}

		$response = new WSDL\sendNotificationResponse();
		$response->notificationResponse = $respstring;

		return $response;
	}

	protected function createAdyenMsgObjFromItem( WSDL\NotificationRequestItem $item ) {
		Logger::info( 'Creating Adyen message object from data.' );
		$msg = AdyenMessage::getInstanceFromWSDL( $item );

		if ( $msg === false ) {
			Logger::error( 'Listener message object could not be created. Unknown type!', $item );
			return false;
		} else {
			$className = get_class( $msg );
			Logger::info( "Listener message of type $className created." );
		}
		return $msg;
	}
}
