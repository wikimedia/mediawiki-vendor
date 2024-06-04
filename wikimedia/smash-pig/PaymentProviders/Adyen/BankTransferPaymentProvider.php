<?php

namespace SmashPig\PaymentProviders\Adyen;

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
		if ( isset( $params['payment_submethod'] ) && $params['payment_submethod'] === 'ach' ) {
			return $this->createACHPayment( $params );
		}
		if ( !empty( $params['issuer_id'] ) ) {
			// one time and initial iDEAL will have an issuer_id set
			$rawResponse = $this->api->createBankTransferPaymentFromCheckout( $params );
		} elseif ( !empty( $params['iban'] ) ) {
			// The IBAN of the bank account for SEPA, do not encrypt
			$rawResponse = $this->api->createSEPABankTransferPayment( $params );
		} else {
			// subsequent recurring will have recurring_payment_token as storedPaymentMethodId,
			// which is the pspReference from the RECURRING_CONTRACT webhook
			$params['payment_method'] = 'sepadirectdebit';
			$params['manual_capture'] = false;
			$rawResponse = $this->api->createPaymentFromToken( $params );
		}
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );
		$rawStatus = $rawResponse['resultCode'];

		$this->mapStatus(
			$response,
			$rawResponse,
			new CreatePaymentStatus(),
			$rawStatus,
			[ FinalStatus::PENDING, FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ]
		);

		if ( $rawStatus === 'RedirectShopper' ) {
			$response->setRedirectUrl( $rawResponse['action']['url'] );
		}
		$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );

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
