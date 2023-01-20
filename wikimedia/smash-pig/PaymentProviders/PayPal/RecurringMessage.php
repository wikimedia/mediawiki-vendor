<?php

namespace SmashPig\PaymentProviders\PayPal;

class RecurringMessage extends Message {

	public static function normalizeMessage( &$message, $ipnMessage ) {
		$message['recurring'] = '1';
		$message['gateway'] = 'paypal_ec';

		// Contact info
		if ( $ipnMessage['txn_type'] === 'recurring_payment_profile_created' || $ipnMessage['txn_type'] === 'recurring_payment' ) {
			$message['middle_name'] = '';

			if ( isset( $ipnMessage['address_street'] ) ) {
				$split = explode( "\n", str_replace( "\r", '', $ipnMessage['address_street'] ) );
				$message['street_address'] = $split[0];
				if ( count( $split ) > 1 ) {
					$message['supplemental_address_1'] = $split[1];
				}

			}
		}

		switch ( $ipnMessage['txn_type'] ) {
			case 'recurring_payment':
				$message['txn_type'] = 'subscr_payment';
				self::mergePendingDetails( $message );
				break;

			case 'recurring_payment_profile_created':
				$message['txn_type'] = 'subscr_signup';
				// break the period out for civicrm
				if ( $ipnMessage['payment_cycle'] == 'Monthly' ) {
					$message['frequency_interval'] = '1';
					$message['frequency_unit'] = 'month';
				}

				$message['installments'] = 0;

				if ( isset( $ipnMessage['time_created'] ) ) {
					if ( $ipnMessage['txn_type'] == 'recurring_payment_profile_created' ) {
						$message['create_date'] = strtotime( $ipnMessage['time_created'] );
						$message['start_date'] = strtotime( $ipnMessage['time_created'] );
					}
					if ( !isset( $message['date'] ) ) {
						$message['date'] = strtotime( $ipnMessage['time_created'] );
					}
				}
				self::mergePendingDetails( $message );
				break;

			case 'recurring_payment_profile_cancel':
				$message['txn_type'] = 'subscr_cancel';
				$message['cancel_date'] = time();
				break;

			# FIXME the last two should actually mark a contribution_recur as failed,
			# while the first three should just record one failed installment. We are
			# recording all of these as one failed installment.
			case 'recurring_payment_failed':
			case 'recurring_payment_outstanding_payment_failed':
			case 'recurring_payment_skipped':
			case 'recurring_payment_suspended':
			case 'recurring_payment_suspended_due_to_max_failed_payment':
				$message['txn_type'] = 'subscr_failed';
				break;

			case 'recurring_payment_expired':
				$message['txn_type'] = 'subscr_eot';
				break;

		}

		if ( !isset( $message['date'] ) ) {
			$message['date'] = time();
		}
	}
}
