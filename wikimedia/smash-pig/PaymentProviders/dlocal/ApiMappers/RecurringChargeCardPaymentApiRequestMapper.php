<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use SmashPig\PaymentProviders\dlocal\ReferenceData;
use UnexpectedValueException;

class RecurringChargeCardPaymentApiRequestMapper extends DirectCardPaymentApiRequestMapper {
	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		$mapperOutput['card']['capture'] = true;

		// update country params for dlocal recurring something wrong with donor country from
		// \CRM_Core_Payment_SmashPigRecurringProcessor::getPaymentParams
		if ( !empty( $params['currency'] ) ) {
			$country = ReferenceData::getPairedCountryFromCurrency( $params['currency'] );
			if ( !$country ) {
				throw new UnexpectedValueException( "Unknown CURRENCY" . $params['currency'] );
			}
			$mapperOutput['country'] = $country;
		}

		return $mapperOutput;
	}
}
