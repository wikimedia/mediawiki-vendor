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

		// We are pretty sure Giving Fund messages will have no corresponding pending entry (they are PayPal-initiated,
		// not from any activity on payments-wiki). Still, we put this after mergePendingDetails to make sure any
		// name / address / email is deleted so as not to change the PayPal Giving Fund contact.
		self::addContactIdWhenGivingFund( $message, $config );
	}

	protected static function addContactIdWhenGivingFund( &$message, $config ) {
		// Tag donations from givingfund email list with the giving fund organization contact ID
		$gfCid = $config->val( 'givingfund-cid' );
		$gfEmails = $config->val( 'givingfund-emails' );
		if ( $gfCid && $gfEmails && in_array( strtolower( $message['email'] ), $gfEmails ) ) {
			$message['contact_id'] = $gfCid;
			$contactFields = [
				'city', 'country', 'email', 'first_name', 'last_name', 'postal_code', 'state_province',
				'street_address', 'supplemental_address_1'
			];
			foreach ( $contactFields as $field ) {
				unset( $message[$field] );
			}
		}
	}
}
