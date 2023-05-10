<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use SmashPig\PaymentProviders\dlocal\Api;

class DirectBankTransferPaymentApiRequestMapper extends PaymentApiRequestMapper {
	public function getInputParameterMap(): array {
		$mapping = parent::getInputParameterMap();
		$mapping = array_merge( $mapping, [
			'wallet' => [
				'account_id' => 'upi_id'
			],
		] );
		return $mapping;
	}

	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		$mapperOutput['payment_method_id'] = 'UD';
		$mapperOutput['payment_method_flow'] = Api::PAYMENT_METHOD_FLOW_DIRECT;
		$mapperOutput['wallet']['token'] = '';

		return $mapperOutput;
	}
}
