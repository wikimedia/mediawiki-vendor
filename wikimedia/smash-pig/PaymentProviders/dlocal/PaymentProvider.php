<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\ICancelablePaymentProvider;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
use SmashPig\PaymentProviders\IRefundablePaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class PaymentProvider implements IGetLatestPaymentStatusProvider, ICancelablePaymentProvider, IRefundablePaymentProvider {
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * @var ProviderConfiguration
	 */
	protected $providerConfiguration;

	public function __construct() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}

	/**
	 * Cancel Payment which is authorized but not captured yet
	 * @param string $gatewayTxnId
	 *
	 * @return \SmashPig\PaymentProviders\Responses\CancelPaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelPayment( string $gatewayTxnId ): CancelPaymentResponse {
		try {
			$result = $this->api->cancelPayment( $gatewayTxnId );
			return DlocalCancelPaymentResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalCancelPaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * Refund a payment
	 * @param array $params
	 *
	 * @return \SmashPig\PaymentProviders\Responses\RefundPaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function refundPayment( array $params ): RefundPaymentResponse {
		try {
			if ( empty( $params['gateway_txn_id'] ) ) {
				return DlocalRefundPaymentResponseFactory::fromErrorResponse( [
					'error' => 'Missing required fields'
				] );
			}
			$apiParams = [];
			if ( !empty( $params['gateway_txn_id'] ) ) {
				$apiParams['payment_id'] = $params['gateway_txn_id'];
			}
			if ( !empty( $params['currency'] ) ) {
				$apiParams['currency'] = $params['currency'];
			}
			if ( !empty( $params['gross'] ) ) {
				$apiParams['amount'] = $params['gross'];
			}
			if ( !empty( $params['amount'] ) ) {
				$apiParams['amount'] = $params['amount'];
			}

			$result = $this->api->refundPayment( $apiParams );
			return DlocalRefundPaymentResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalRefundPaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * Get Payment detail which could contain recurring bt token
	 * @param string $gatewayTxnId
	 *
	 * @return \SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentDetail( string $gatewayTxnId ): PaymentProviderExtendedResponse {
		try {
			$result = $this->api->getPaymentDetail( $gatewayTxnId );
			return DlocalCreatePaymentResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalCreatePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * @param array $params
	 * recurring charge
	 * @return \SmashPig\PaymentProviders\Responses\CreatePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPaymentFromToken( array $params ): CreatePaymentResponse {
		try {
			$result = $this->api->createPaymentFromToken( $params );
			$response = DlocalCreatePaymentResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalCreatePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
		return $response;
	}

	/**
	 * @param array $params
	 *
	 * @return \SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getLatestPaymentStatus( array $params ): PaymentProviderExtendedResponse {
		try {
			$result = $this->api->getPaymentStatus( $params['gateway_txn_id'] );
			return DlocalPaymentStatusResponseFactory::fromRawResponse( $result );
		} catch ( ApiException $apiException ) {
			return DlocalPaymentStatusResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	/**
	 * @throws ValidationException
	 */
	protected static function checkFields( $requiredFields, $input ) {
		$invalidFields = [];
		foreach ( $requiredFields as $field ) {
			if ( empty( $input[$field] ) ) {
				$invalidFields[$field] = 'required';
			}
		}

		if ( count( $invalidFields ) ) {
			throw new ValidationException( "Invalid input", $invalidFields );
		}
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $error
	 * @return void
	 */
	protected static function handleValidationException( PaymentProviderResponse $paymentResponse, array $error ): void {
		self::addPaymentResponseValidationErrors( $error, $paymentResponse );
		$paymentResponse->setStatus( FinalStatus::FAILED );
		$paymentResponse->setSuccessful( false );
	}

	/**
	 * @param array $missingParams
	 * @param PaymentProviderResponse $paymentResponse
	 * @return void
	 */
	protected static function addPaymentResponseValidationErrors(
		array $params, PaymentProviderResponse $paymentResponse
	): void {
		foreach ( $params as $param => $message ) {
			$paymentResponse->addValidationError(
				new ValidationError( $param, null, [], $message )
			);
		}
	}

	/**
	 * Adds default params when none were entered
	 * Currently focused on fiscal number
	 * This is copied from PlaceholderFiscalNumber.php in Donation Interface
	 *
	 * TODO: Find this a better place to live (maybe)
	 *
	 */
	protected function addPlaceholderCreateHostedPaymentParams( &$params ): void {
		$placeholders = [
			'MX' => [ 1.0e+12, 1.0e+13 ],
			'PE' => [ 1.0e+8, 1.0e+10 ],
			'IN' => 'AABBC1122C', // DLOCAL-specific PAN. See T258086
			'ZA' => 'AABBC1122C'  // DLOCAL-specific default for empty cpf. See T307743
		];

		if (
			empty( $params['fiscal_number'] ) &&
			isset( $params['country'] ) &&
			array_key_exists( $params['country'], $placeholders )
		) {
			$country = $params['country'];
			$fiscalNumber = $placeholders[$country];

			// if placeholder is an array we use the values as upper and lower range bounds
			if ( is_array( $fiscalNumber ) ) {
				$lower = $fiscalNumber[0];
				$upper = $fiscalNumber[1];
				$fiscalNumber = mt_rand( $lower, $upper );
			}

			$params['fiscal_number'] = $fiscalNumber;
		}
		if ( isset( $params['recurring'] ) && $params['recurring'] ) {
			$params['description'] = 'Wikimedia Foundation (Recurring)';
		}
	}
}
