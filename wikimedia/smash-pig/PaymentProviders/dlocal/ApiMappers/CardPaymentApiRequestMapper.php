<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use SmashPig\PaymentProviders\dlocal\Api;

class CardPaymentApiRequestMapper extends PaymentApiRequestMapper {

	public function getInputParameterMap(): array {
		$mapping = parent::getInputParameterMap();
		$mapping = array_merge( $mapping, [
			'card' => [
				'token' => 'payment_token',
				'card_id' => 'recurring_payment_token'
			]
		] );
		return $mapping;
	}

	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		$isRecurring = $params['recurring'] ?? false;
		$use3DSecure = array_key_exists( 'use_3d_secure', $params ) && $params['use_3d_secure'] === true;

		$mapperOutput['payment_method_id'] = Api::PAYMENT_METHOD_ID_CARD;
		if ( $isRecurring ) {
			$mapperOutput['card']['save'] = true;
		}

		if ( $use3DSecure ) {
			$mapperOutput['three_dsecure'] = [
				'force' => true
			];
		}

		return $mapperOutput;
	}
}
