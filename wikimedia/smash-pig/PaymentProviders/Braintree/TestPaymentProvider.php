<?php

namespace SmashPig\PaymentProviders\Braintree;

/**
 * This class allows testing connectivity with the Braintree GraphQL endpoint.
 * See the TestApi script under PaymentProviders/Braintree/Maintenance.
 */
class TestPaymentProvider extends PaymentProvider {

	public function ping(): string {
		$response = $this->api->ping();
		return $response['data']['ping'];
	}
}
