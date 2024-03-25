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

	public static function getSuccessfulACHResult( $id ): array {
		return [
			'additionalData' => [
				'achdirectdebit.dateOfSignature' => '2024-02-26',
				'achdirectdebit.mandateId' => 'NTLGLFS8C6PFWR82',
				'achdirectdebit.sequenceType' => 'OneOff'
			],
			'pspReference' => 'NTLGLFS8C6PFWR82',
			'resultCode' => 'Authorised',
			'amount' => [
				'currency' => 'USD',
				'value' => 8500,
			],
			'merchantReference' => $id,
		];
	}

	public static function getErrorCreatePaymentResult() {
		return [
			'additionalData' => [
				'cvcResult' => 3,
				'avsResult' => 'Unavailable',
			],
			'pspReference' => 'MOCK_REFERENCE',
			'resultCode' => 'Error',
			'refusalReason' => 'Acquirer Error'
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
