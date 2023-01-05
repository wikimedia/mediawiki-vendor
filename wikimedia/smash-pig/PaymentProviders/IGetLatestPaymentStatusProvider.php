<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

interface IGetLatestPaymentStatusProvider {
	/**
	 * @param array $params
	 * @return PaymentDetailResponse
	 */
	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse;

}
