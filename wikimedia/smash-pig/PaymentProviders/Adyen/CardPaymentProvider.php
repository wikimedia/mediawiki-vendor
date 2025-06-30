<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class CardPaymentProvider extends PaymentProvider {

	/**
	 * Request authorization of a credit card payment
	 *
	 * @param array $params
	 *  for a recurring installment, needs
	 *  * 'recurring_payment_token'
	 *  * 'order_id'
	 *  * 'recurring'
	 *  * 'amount'
	 *  * 'currency'
	 * for a payment from encrypted card details, needs
	 *  * 'encrypted_payment_data' with subkeys from Checkout UI
	 *  * 'order_id'
	 *  * 'amount'
	 *  * 'currency'
	 * to trigger 3D Secure on a card payment (for a card that has it enabled), please set
	 *  * 'return_url'
	 *  * 'browser_info' with subkeys from Checkout UI
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		if ( !empty( $params['encrypted_payment_data'] ) ) {
			return $this->createPaymentFromEncryptedDetails( $params );
		} elseif ( !empty( $params['recurring_payment_token'] ) && !empty( $params['processor_contact_id'] ) ) {
			return $this->createRecurringPaymentWithShopperReference( $params );
		} else {
			throw new \RuntimeException(
				'Authorization needs either encrypted_payment_data, or both ' .
				'recurring_payment_token and processor_contact_id'
			);
		}
	}

	protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer {
		return new ApprovalNeededCreatePaymentStatus();
	}

	protected function getPaymentDetailsSuccessfulStatuses(): array {
		return [ FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ];
	}

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	protected function createPaymentFromEncryptedDetails( array $params ): CreatePaymentResponse {
		$rawResponse = $this->api->createPaymentFromEncryptedDetails(
			$params
		);
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );
		$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );

		$rawStatus = $rawResponse['resultCode'] ?? null;
		$this->mapStatus(
			$response,
			$rawResponse,
			new ApprovalNeededCreatePaymentStatus(),
			$rawStatus
		);
		if ( $rawStatus === 'RedirectShopper' ) {
			$response->setRedirectUrl( $rawResponse['action']['url'] )
				->setRedirectData( $rawResponse['action']['data'] );
		} else {
			if ( isset( $rawResponse['additionalData'] ) ) {
				$this->mapAdditionalData( $rawResponse['additionalData'], $response );
			} elseif ( !$response->hasErrors() ) {
				// We expect additionalData on responses with no errors and no redirect
				Logger::warning(
					'additionalData missing from Adyen createPayment response, so ' .
					'no risk score for avs and cvv',
					$rawResponse
				);
			}
		}

		return $response;
	}

}
