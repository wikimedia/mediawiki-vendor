<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class EWalletPaymentProvider extends PaymentProvider {

	/**
	 * Create an e-wallet payment.
	 * Initially only Vipps is supported
	 * https://docs.adyen.com/payment-methods/vipps/web-component
	 *
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		$response = new CreatePaymentResponse();
		try {
			$submethod = $params['payment_submethod'] ?? null;
			$validationErrors = $this->validateParams( $submethod, $params );
			if ( !empty( $validationErrors ) ) {
				$response->setSuccessful( false );
				$response->setStatus( FinalStatus::FAILED );
				foreach ( $validationErrors as $error ) {
					$response->addValidationError( $error );
				}
				return $response;
			}
			$rawResponse = $this->api->createEWalletPaymentFromCheckout( $params );

			$response->setRawResponse( $rawResponse );
			$rawStatus = $rawResponse['resultCode'];
			$statusMapper = new CreatePaymentStatus();

			$this->mapStatus(
				$response,
				$rawResponse,
				$statusMapper,
				$rawStatus,
				[ FinalStatus::PENDING, FinalStatus::COMPLETE ]
			);

			if ( $rawStatus === 'RedirectShopper' ) {
				$response->setRedirectUrl( $rawResponse['action']['url'] );
			}
			$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );
			if ( isset( $rawResponse['additionalData'] ) ) {
				$this->mapAdditionalData( $rawResponse['additionalData'], $response );
			}
		} catch ( \Exception $ex ) {
			$response->setSuccessful( false );
			$response->setStatus( FinalStatus::FAILED );
			$response->addErrors(
				new PaymentError(
					ErrorCode::UNKNOWN,
					$ex->getMessage(),
					LogLevel::INFO
				)
			);
		}
		return $response;
	}

	protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer {
		return new RedirectedPaymentStatus();
	}

	protected function getPaymentDetailsSuccessfulStatuses(): array {
		return [ FinalStatus::PENDING, FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ];
	}

	protected function validateParams( ?string $submethod, array $params ): array {
		$errors = [];
		switch ( $submethod ) {
			case 'vipps':
				if ( empty( $params['phone'] ) ) {
					$errors[] = new ValidationError(
						'phone', null, [],
						"Missing required field 'phone'"
					);
				}
				if ( ( $params['currency'] ?? '' ) !== 'NOK' ) {
					$errors[] = new ValidationError(
						'currency', null, [],
						"Missing or unsupported currency '{$params['currency']}'"
					);
				}
				break;
			default:
				$errors[] = new ValidationError(
					'payment_submethod', null, [],
					"Unsupported or missing payment_submethod '$submethod'"
				);
				break;
		}
		return $errors;
	}
}
