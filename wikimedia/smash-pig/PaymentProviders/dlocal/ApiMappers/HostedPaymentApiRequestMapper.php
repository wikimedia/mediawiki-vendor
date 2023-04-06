<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use DateTime;
use DateTimeZone;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\BankTransferPaymentProvider;
use UnexpectedValueException;

class HostedPaymentApiRequestMapper extends PaymentApiRequestMapper {

	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		// Set custom parameters
		$mapperOutput['payment_method_flow'] = Api::PAYMENT_METHOD_FLOW_REDIRECT;

		// For India recurring, we need to create a monthly subscription with India time zone
		if ( BankTransferPaymentProvider::isIndiaRecurring( $params ) ) {
			$mapperOutput['wallet']['save'] = true;
			$mapperOutput['wallet']['capture'] = true;
			$mapperOutput['wallet']['verify'] = false;
			$mapperOutput['wallet']['username'] = $mapperOutput['payer']['name'];
			$mapperOutput['wallet']['email'] = $params['email'];
			$this->validateAndMapFrequencyUnit( $params, $mapperOutput );
			$mapperOutput['wallet']['recurring_info']['subscription_frequency'] = 1;
			$date = new DateTime( 'now', new DateTimeZone( Api::INDIA_TIME_ZONE ) );
			$mapperOutput['wallet']['recurring_info']['subscription_start_at'] = $date->format( 'Ymd' );
			$mapperOutput['wallet']['recurring_info']['subscription_max_amount'] = $params['amount']; // set the max recurring amount to init donation's amount
			$this->validateAndMapSubscriptionEnd( $params, $mapperOutput );
		}
		return $mapperOutput;
	}

	protected function validateAndMapFrequencyUnit( $params, &$mapperOutput ) {
		$unit = $params['inr_subscription_frequency'] ?? BankTransferPaymentProvider::SUBSCRIPTION_FREQUENCY_UNIT_ONDEMAND;
		if ( !in_array( $unit,
			[ BankTransferPaymentProvider::SUBSCRIPTION_FREQUENCY_UNIT_ONDEMAND, BankTransferPaymentProvider::SUBSCRIPTION_FREQUENCY_UNIT_MONTHLY ]
		) ) {
			throw new UnexpectedValueException(
				'Bad inr_subscription_frequency ' . $unit
			);
		}
		$mapperOutput['wallet']['recurring_info']['subscription_frequency_unit'] = $unit;
	}

	protected function validateAndMapSubscriptionEnd( $params, &$mapperOutput ) {
		if ( empty( $params['inr_subscription_months'] ) ) {
			$subscriptionEnd = '20991231'; // if more than year 2100, dlocal reject txn so use 20991231
		} else {
			if ( !is_int( $params['inr_subscription_months'] ) ) {
				throw new UnexpectedValueException(
					'Bad inr_subscription_months ' . $params['inr_subscription_months']
				);
			}
			$endDate = new DateTime(
				"+ {$params['inr_subscription_months']} months", new DateTimeZone( Api::INDIA_TIME_ZONE )
			);
			$subscriptionEnd = $endDate->format( 'Ymd' );
		}
		$mapperOutput['wallet']['recurring_info']['subscription_end_at'] = $subscriptionEnd;
	}

}
