<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

class CapturePaymentApiRequestMapper extends ApiRequestMapper {
	/**
	 * @var array
	 */
	protected $parameterMap = [
		'authorization_id' => 'gateway_txn_id',
		'amount' => 'amount',
		'currency' => 'currency',
		'order_id' => 'order_id',
	];

}
