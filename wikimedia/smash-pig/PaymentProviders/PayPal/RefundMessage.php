<?php

namespace SmashPig\PaymentProviders\PayPal;

class RefundMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		// We gave the money back ourselves, or PayPal gave no reason
		$refundReasonCodes = [
			'refund',
			'other',
		];
		// The donor disputed the payment, or PayPal pulled it back for fraud.
		// Recording these as chargebacks lets Civi cancel the recurring.
		$chargebackReasonCodes = [
			'chargeback',
			'buyer_complaint',
			'unauthorized_spoof',
			'unauthorized_claim',
			'admin_fraud_reversal',
			'admin_reversal',
		];

		$message['gateway_refund_id'] = $ipnMessage['txn_id'];
		$message['gross_currency'] = $ipnMessage['mc_currency'];

		if ( isset( $message['txn_type'] ) && $message['txn_type'] === 'adjustment' ) {
			$message['type'] = 'chargeback';

		} elseif ( isset( $ipnMessage['payment_status'] ) && $ipnMessage['payment_status'] === 'Canceled_Reversal' ) {
			// PayPal canceled the reversal and restored funds to the account
			$message['type'] = 'reversal_reversed';

		} elseif ( isset( $ipnMessage['reason_code'] ) && in_array( $ipnMessage['reason_code'], $chargebackReasonCodes ) ) {
			$message['type'] = 'chargeback';

		} elseif ( isset( $ipnMessage['reason_code'] ) && in_array( $ipnMessage['reason_code'], $refundReasonCodes ) ) {
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
