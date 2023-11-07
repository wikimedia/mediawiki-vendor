<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class ApprovePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class ApprovePaymentResponse extends PaymentProviderResponse {

	/**
	 * Net amount, may have been converted to settlement currency
	 * @var numeric|null
	 */
	protected $settledAmount;

	/**
	 * Settlement currency
	 * @var string|null
	 */
	protected $settledCurrency;

	/**
	 * @var numeric|null
	 */
	protected $fee;

	/**
	 * If not given, fee is assumed to be in the settlement currency
	 * @var string|null
	 */
	protected $feeCurrency;
}
