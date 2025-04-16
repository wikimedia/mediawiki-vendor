<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

interface IGetLatestPaymentStatusProvider {
	/**
	 * @param array $params
	 * @return PaymentProviderExtendedResponse
	 */
	public function getLatestPaymentStatus( array $params ): PaymentProviderExtendedResponse;

}
