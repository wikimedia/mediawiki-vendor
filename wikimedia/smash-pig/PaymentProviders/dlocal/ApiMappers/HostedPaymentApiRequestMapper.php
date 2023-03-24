<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use DateTime;
use DateTimeZone;
use SmashPig\PaymentProviders\dlocal\Api;
use UnexpectedValueException;

class HostedPaymentApiRequestMapper extends PaymentApiRequestMapper {

	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		// Set custom parameters
		$mapperOutput['payment_method_flow'] = Api::PAYMENT_METHOD_FLOW_REDIRECT;

		// For UPI recurring, we need to create a monthly subscription with India time zone
		if ( Api::isRecurringUpi( $params ) ) {
			$mapperOutput['wallet']['save'] = true;
			$mapperOutput['wallet']['capture'] = true;
			$mapperOutput['wallet']['verify'] = false;
			$mapperOutput['wallet']['username'] = $mapperOutput['payer']['name'];
			$mapperOutput['wallet']['email'] = $params['email'];
			$this->verifyAndMapFrequencyUnit( $params, $mapperOutput );
			$mapperOutput['wallet']['recurring_info']['subscription_frequency'] = 1;
			$date = new DateTime( 'now', new DateTimeZone( Api::INDIA_TIME_ZONE ) );
			$mapperOutput['wallet']['recurring_info']['subscription_start_at'] = $date->format( 'Ymd' );
			$mapperOutput['wallet']['recurring_info']['subscription_end_at'] = '20991231'; // if more than year 2100, dlocal reject txn so use 20991231
		}
		return $mapperOutput;
	}

	protected function verifyAndMapFrequencyUnit( $params, &$mapperOutput ) {
		$unit = $params['upi_subscription_frequency'] ?? Api::SUBSCRIPTION_FREQUENCY_UNIT_ONDEMAND;
		if ( !in_array( $unit,
			[ Api::SUBSCRIPTION_FREQUENCY_UNIT_ONDEMAND, Api::SUBSCRIPTION_FREQUENCY_UNIT_MONTHLY ]
		) ) {
			throw new UnexpectedValueException(
				'Bad upi_subscription_frequency ' . $unit
			);
		}
		$mapperOutput['wallet']['recurring_info']['subscription_frequency_unit'] = $unit;
	}

}
