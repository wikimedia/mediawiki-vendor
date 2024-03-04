<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class ACHDirectDebitPaymentProvider extends PaymentProvider {

	public function createPayment( array $params ) : CreatePaymentResponse {
		$rawResponse = $this->api->createACHDirectDebitPayment( $params );
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

	/**
	 * This method should never be called, since ACH does not include any flow in
	 * which the user is redirected to an external site and then returns to ours. (That's
	 * where a payment details status normalizer would be used.)
	 *
	 * {@inheritDoc}
	 * @see \SmashPig\PaymentProviders\Adyen\PaymentProvider::getPaymentDetailsStatusNormalizer()
	 */
	protected function getPaymentDetailsStatusNormalizer() : StatusNormalizer {
		throw new \BadMethodCallException( 'No payment details status normalizer for ACH direct debit.' );
	}

	protected function getPaymentDetailsSuccessfulStatuses(): array {
		return [ FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ];
	}
}
