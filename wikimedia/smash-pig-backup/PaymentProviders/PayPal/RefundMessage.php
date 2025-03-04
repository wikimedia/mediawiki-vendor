<?php

namespace SmashPig\PaymentProviders\PayPal;

class RefundMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		$reasonCodes = [
			'refund',
			'buyer_complaint',
			'other',
			'unauthorized_spoof',
			'unauthorized_claim',
			'admin_fraud_reversal'
		];

		$message['gateway_refund_id'] = $ipnMessage['txn_id'];
		$message['gross_currency'] = $ipnMessage['mc_currency'];

		if ( isset( $message['txn_type'] ) && $message['txn_type'] === 'adjustment' ) {
			$message['type'] = 'chargeback';

		} elseif ( isset( $ipnMessage['reason_code'] ) && in_array( $ipnMessage['reason_code'], $reasonCodes ) ) {
			$message['type'] = 'refund';

		}

		// Express checkout sets the 'invoice' field, legacy doesn't.
		// EC refunds of recurring payments use 'rp_invoice_id'
		if ( isset( $ipnMessage['invoice'] ) || isset( $ipnMessage['rp_invoice_id'] ) ) {
			$message['gateway'] = 'paypal_ec';
		} else {
			$message['gateway'] = 'paypal';
		}
	}
}
