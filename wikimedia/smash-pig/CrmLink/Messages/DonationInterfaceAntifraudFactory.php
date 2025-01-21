<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\UtcDate;

/**
 * Message encapsulating fraud scores and outcome
 */
class DonationInterfaceAntifraudFactory {

	public static function create(
		$donationMessage,
		$riskScore,
		$scoreBreakdown = [],
		$validationAction = 'process'
	) {
		$date = $donationMessage['date'];
		if ( is_int( $date ) ) {
			$timestamp = $date;
		} else {
			$timestamp = UtcDate::getUtcTimestamp( $date );
		}
		$antifraud = [
			'risk_score' => $riskScore,
			'score_breakdown' => $scoreBreakdown,
			'validation_action' => $validationAction,
			'date' => $timestamp,
		];

		$keysToCopy = [
			'contribution_tracking_id',
			'gateway',
			'order_id',
			'payment_method',
			'user_ip'
			// no 'server' available
		];

		foreach ( $keysToCopy as $key ) {
			$antifraud[$key] = $donationMessage[$key];
		}
		return $antifraud;
	}
}
