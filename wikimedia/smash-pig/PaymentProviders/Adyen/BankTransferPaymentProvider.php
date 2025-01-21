<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class BankTransferPaymentProvider extends PaymentProvider {

	/**
	 * Create a Bank Transfer payment onlineBanking_CZ for one-time, with Adyen Checkout
	 * OR initial payments will be type SEPA Direct Debit or iDEAL, which ideal use
	 * SEPA Direct Debit for subsequent recurring
	 * https://docs.adyen.com/payment-methods/ideal/web-component
	 * https://docs.adyen.com/payment-methods/online-banking-czech-republic/web-component
	 *
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		$response = new CreatePaymentResponse();
		try {
			$submethod = $params['payment_submethod'] ?? null;
			if ( $submethod === 'ach' ) {
				return $this->createACHPayment( $params );
			}
			if ( !empty( $params['recurring_payment_token'] ) ) {
				// subsequent recurring will have recurring_payment_token as storedPaymentMethodId,
				// which is the pspReference from the RECURRING_CONTRACT webhook
				$params['payment_method'] = 'sepadirectdebit';
				$params['manual_capture'] = false;
				$rawResponse = $this->api->createPaymentFromToken( $params );
				$recurringTokenDelayed = false;
			} elseif ( isset( $params['issuer_id'] ) || $submethod === 'rtbt_ideal' ) {
				// one time and initial CZ online banking will have an issuer_id set
				// iDEAL 1.0 has issuer_id - to use iDEAL 2.0 just send payment_submethod=rtbt_ideal
				$rawResponse = $this->api->createBankTransferPaymentFromCheckout( $params );
				$recurringTokenDelayed = true;
			} elseif ( !empty( $params['iban'] ) ) {
				// The IBAN of the bank account for SEPA, do not encrypt
				$rawResponse = $this->api->createSEPABankTransferPayment( $params );
				$recurringTokenDelayed = true;
			} else {
				$response->setSuccessful( false );
				$response->setStatus( FinalStatus::FAILED );
				$response->addErrors( [
					new PaymentError(
						ErrorCode::VALIDATION,
						'Bad parameters: need to either set payment_submethod to ach or rtbt_ideal, or set one of ' .
						'recurring_payment_token, iban, or issuer_id.',
						LogLevel::ERROR
					)
				] );
				return $response;
			}
			$response->setRawResponse( $rawResponse );
			$rawStatus = $rawResponse['resultCode'];
			// When we are creating an initial recurring bank transfer payment, we do not get
			// the recurring token on the createPayment response so we can't call the payment
			// complete. For these payments the initial successful response should be pending.
			if ( $recurringTokenDelayed && !empty( $params['recurring'] ) ) {
				$statusMapper = new DelayedTokenStatus();
			} else {
				$statusMapper = new CreatePaymentStatus();
			}

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

	protected function createACHPayment( array $params ) : CreatePaymentResponse {
		if ( !empty( $params['recurring_payment_token'] ) ) {
			$params['payment_method'] = 'ach';
			$rawResponse = $this->api->createPaymentFromToken( $params );
		} else {
			$rawResponse = $this->api->createACHDirectDebitPayment( $params );
		}
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );

		$this->mapStatus(
			$response,
			$rawResponse,
			new ApprovalNeededCreatePaymentStatus(),
			$rawResponse['resultCode'] ?? null
		);
		$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );
		// additionalData has the recurring details
		if ( isset( $rawResponse['additionalData'] ) ) {
			$this->mapAdditionalData( $rawResponse['additionalData'], $response );
		}

		return $response;
	}

	protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer {
		return new RedirectedPaymentStatus();
	}

	protected function getPaymentDetailsSuccessfulStatuses(): array {
		return [ FinalStatus::PENDING, FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ];
	}
}
