<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\DeleteDataResponse;

interface IDeleteDataProvider {
	public function deleteDataForPayment( string $gatewayTransactionId ): DeleteDataResponse;
}
