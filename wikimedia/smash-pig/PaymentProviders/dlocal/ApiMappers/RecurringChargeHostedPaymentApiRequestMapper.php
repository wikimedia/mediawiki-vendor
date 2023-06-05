<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\ReferenceData;

class RecurringChargeHostedPaymentApiRequestMapper extends PaymentApiRequestMapper {

	public function getInputParameterMap(): array {
		$mapping = parent::getInputParameterMap();
		$mapping = array_merge( $mapping, [
			'wallet' => [
				'token' => 'recurring_payment_token',
			],
		] );
		return $mapping;
	}

	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		$customDescription = $params['description'] ?? 'recurring charge';
		$mapperOutput['description'] = $customDescription;
		$mapperOutput['payment_method_flow'] = Api::PAYMENT_METHOD_FLOW_DIRECT;
		$mapperOutput['wallet']['recurring_info']['prenotify'] = true;
		if ( !empty( $params['currency'] ) ) {
			$country = ReferenceData::getPairedCountryFromCurrency( $params['currency'] );
			if ( !$country ) {
				throw new UnexpectedValueException( "Unknown CURRENCY" . $params['currency'] );
			}
		}
		$mapperOutput['country'] = $country;
		return $mapperOutput;
	}
}
