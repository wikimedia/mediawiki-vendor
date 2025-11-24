<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\CancelAutoRescueResponse;

interface ICancelAutoRescueProvider {
	/**
	 * @param array $params
	 * @return bool
	 */
	public function cancelAutoRescue( string $rescueReference ): CancelAutoRescueResponse;
}
