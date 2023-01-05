<?php

namespace SmashPig\PaymentProviders\PayPal;

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

		self::mergePendingDetails( $message );
	}
}
