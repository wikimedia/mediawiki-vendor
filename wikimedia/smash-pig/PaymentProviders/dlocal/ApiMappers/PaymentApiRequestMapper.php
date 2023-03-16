<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

class PaymentApiRequestMapper extends ApiRequestMapper {
	protected $parameterMap = [
		'amount' => 'amount',
		'currency' => 'currency',
		'country' => 'country',
		'order_id' => 'order_id',
		'payment_method_flow' => 'payment_method_flow',
		'payment_method_id' => 'payment_method_id',
		'payer' => [
			'email' => 'email',
			'document' => 'fiscal_number',
			'user_reference' => 'contact_id',
			'ip' => 'user_ip',
			'address' => [
				'state' => 'state_province',
				'city' => 'city',
				'zip_code' => 'postal_code',
				'street' => 'street_address',
				'number' => 'street_number',
			],
		],
		'description' => 'description',
		'callback_url' => 'return_url',
		'notification_url' => 'notification_url'
	];

	public function setCustomParameters( $params, &$mapperOutput ): array {
		parent::setCustomParameters( $params, $mapperOutput );

		// Set custom parameters
		$mapperOutput['payer']['name'] = $params['first_name'] . ' ' . $params['last_name'];

		return $mapperOutput;
	}

}
