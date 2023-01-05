<?php

namespace SmashPig\PaymentProviders\Ingenico;

/**
 * Simple class meant to verify connectivity and account setup.
 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/services/testconnection.html?paymentPlatform=ALL#services-testconnection
 *
 * @package SmashPig\PaymentProviders\Ingenico
 */
class TestConnectionProvider extends PaymentProvider {
	public function testConnection(): array {
		$path = "services/testconnection";
		return $this->api->makeApiCall( $path, 'GET' );
	}
}
