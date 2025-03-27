<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use SmashPig\PaymentProviders\dlocal\Api;

class RedirectPaymentApiRequestMapper extends PaymentApiRequestMapper {
	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );
		$mapperOutput['payment_method_flow'] = Api::PAYMENT_METHOD_FLOW_REDIRECT;

		return $mapperOutput;
	}
}
