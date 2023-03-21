<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use DateTime;
use DateTimeZone;
use SmashPig\PaymentProviders\dlocal\Api;

class HostedPaymentApiRequestMapper extends PaymentApiRequestMapper {

	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		// Set custom parameters
		$mapperOutput['payment_method_flow'] = Api::PAYMENT_METHOD_FLOW_REDIRECT;
		$date = new DateTime( 'now', new DateTimeZone( Api::INDIA_TIME_ZONE ) );

		// Set UPI recurring parameters
		$isRecurring = $params['recurring'] ?? '';
		// if recurring, needs to create a monthly subscription with in time zone
		if ( $isRecurring ) {
			$mapperOutput['wallet']['save'] = true;
			$mapperOutput['wallet']['capture'] = true;
			$mapperOutput['wallet']['verify'] = false;
			$mapperOutput['wallet']['username'] = $mapperOutput['payer']['name'];
			$mapperOutput['wallet']['email'] = $params['email'];

			// 'ONDEMAND' has less limitation for prenotify compare with 'MONTH'
			// ( allow recharge send on the same month, since needs 2 days to process),
			// while we need to add a text for client to indicate this is only monthly
			$mapperOutput['wallet']['recurring_info']['subscription_frequency_unit'] = Api::SUBSCRIPTION_FREQUENCY_UNIT;
			$mapperOutput['wallet']['recurring_info']['subscription_frequency'] = 1;
			$mapperOutput['wallet']['recurring_info']['subscription_start_at'] = $date->format( 'Ymd' );
			$mapperOutput['wallet']['recurring_info']['subscription_end_at'] = '20991231'; // if more than year 2100, dlocal reject txn so use 20991231
		}
		return $mapperOutput;
	}

}
