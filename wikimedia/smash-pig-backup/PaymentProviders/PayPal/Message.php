<?php namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;
use SmashPig\CrmLink\Messages\SourceFields;

/**
 * abstract static inheritance? Whatamidoing?
 */
abstract class Message {
	public static function fromIpnMessage( $ipnArray ) {
		$config = Context::get()->getProviderConfiguration();

		$message = [];
		$map = $config->val( 'var_map' );
		foreach ( $map as $rx => $tx ) {
			if ( array_key_exists( $rx, $ipnArray ) ) {
				$message[$tx] = $ipnArray[$rx];
			}
		}

		if (
			isset( $message['order_id'] ) &&
			!isset( $message['contribution_tracking_id'] )
		) {
			$parts = explode( '.', $message['order_id'] );
			$message['contribution_tracking_id'] = $parts[0];
		}

		// If someone's PayPal account is set to their name we don't want
		// it to go in the address box. They should put in a business name
		// or something.
		if ( isset( $message['supplemental_address_1'] )
			&& $message['supplemental_address_1'] ===
			"{$message['first_name']} {$message['last_name']}" ) {
			unset( $message['supplemental_address_1'] );
		}

		// TODO: once recurring messages are normalized with var_map,
		// always do the strtotime
		if ( isset( $message['date'] ) ) {
			$message['date'] = strtotime( $message['date'] );
		}

		static::normalizeMessage( $message, $ipnArray );

		return $message;
	}

	public static function normalizeMessage( &$message, $ipnArray ) {
	}

	protected static function mergePendingDetails( &$message ) {
		// Add in any details left on the pending pile.
		if ( isset( $message['order_id'] ) && isset( $message['gateway'] ) ) {
			$pendingDb = PendingDatabase::get();
			Logger::debug(
				'Searching for pending message with gateway ' .
				"{$message['gateway']} and order id {$message['order_id']}"
			);
			$pendingMessage = $pendingDb->fetchMessageByGatewayOrderId(
				$message['gateway'], $message['order_id']
			);
			if ( $pendingMessage ) {
				Logger::debug( 'Found pending message' );
				SourceFields::removeFromMessage( $pendingMessage );
				unset( $pendingMessage['pending_id'] );
				foreach ( $pendingMessage as $pendingField => $pendingValue ) {
					if ( !isset( $message[$pendingField] ) ) {
						$message[$pendingField] = $pendingValue;
					}
				}
			} else {
				Logger::debug( 'Did not find pending message' );
			}
		} else {
			Logger::debug( 'Missing gateway or order id, skipping pending' );
		}
	}
}
