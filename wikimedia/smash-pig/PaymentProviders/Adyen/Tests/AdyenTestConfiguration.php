<?php namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\GlobalConfiguration;
use SmashPig\Tests\TestingProviderConfiguration;

class AdyenTestConfiguration extends TestingProviderConfiguration {

	public static function instance( $mockApi, GlobalConfiguration $globalConfig ) {
		$config = static::createForProvider( 'adyen', $globalConfig );
		$config->objects['api'] = $mockApi;
		return $config;
	}

	public static function getSuccessfulApproveResult() {
		return [
			'pspReference' => '00000000000000AB',
			'status' => 'received',
		];
	}

	public static function getSuccessfulGoogleResult( $id ): array {
		return [
			'additionalData' => [
				'cvcResult' => '6 No CVC/CVV provided',
				'authCode' => '099013',
				'avsResult' => '2 Neither postal code nor address match',
				'scaExemptionRequested' => 'lowValue',
			],
			'pspReference' => '00000000000000AB',
			'resultCode' => 'Authorised',
			'amount' => [
				'currency' => 'USD',
				'value' => 1000,
			],
			'merchantReference' => $id,
		];
	}

	public static function getSuccessfulCancelResult() {
		return [
			'merchantAccount' => 'WikimediaCOM',
			'paymentReference' => '',
			'pspReference' => '00000000000000AB',
			'status' => 'received',
		];
	}

	public static function getSuccessfulCancelAutoRescueResult() {
		return [
			'response' => '[cancel-received]',
			'pspReference' => '00000000000000CR',
		];
	}
}
