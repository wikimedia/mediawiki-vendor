<?php

namespace SmashPig\PaymentProviders;

interface ICancelablePaymentProvider {
	/**
	 * @param string $gatewayTxnId
	 *
	 * @return CancelPaymentResponse
	 */
	public function cancelPayment( string $gatewayTxnId ) : CancelPaymentResponse;

}
