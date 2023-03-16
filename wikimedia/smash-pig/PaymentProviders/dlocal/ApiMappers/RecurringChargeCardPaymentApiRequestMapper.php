<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

class RecurringChargeCardPaymentApiRequestMapper extends DirectCardPaymentApiRequestMapper {
	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		$mapperOutput['card']['capture'] = true;

		return $mapperOutput;
	}
}
