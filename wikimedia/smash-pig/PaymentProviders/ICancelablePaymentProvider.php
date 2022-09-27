<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;

interface ICancelablePaymentProvider {
	/**
	 * @param string $gatewayTxnId
	 *
	 * @return CancelPaymentResponse
	 */
	public function cancelPayment( string $gatewayTxnId ) : CancelPaymentResponse;

}
