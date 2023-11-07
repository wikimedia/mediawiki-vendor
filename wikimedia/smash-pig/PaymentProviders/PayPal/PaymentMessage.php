<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

class PaymentMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		if (
			$ipnMessage['txn_type'] === 'express_checkout' ||
			(
				$ipnMessage['txn_type'] === 'cart' &&
				$ipnMessage['payment_type'] === 'instant'
			)
		) {
			$message['gateway'] = 'paypal_ec';
		} else {
			$message['gateway'] = 'paypal';
		}
		$config = Context::get()->getProviderConfiguration();
		$glAppeal = $config->val( 'givelively-appeal' );
		// Donations from the GiveLively platform have no 'custom' field (i.e. order ID), and
		// come in with txn_type 'cart', while most front-end donations have 'express_checkout'.
		$isProbableGiveLively = ( empty( $ipnMessage['custom'] ) && $ipnMessage['txn_type'] === 'cart' );
		if ( $glAppeal && $isProbableGiveLively ) {
			Logger::info( 'Tagging IPN from a GiveLively donation, per https://phabricator.wikimedia.org/T295726' );
			$message['direct_mail_appeal'] = $glAppeal;
			$message['no_thank_you'] = 'GiveLively';
		}

		self::mergePendingDetails( $message );
	}
}
