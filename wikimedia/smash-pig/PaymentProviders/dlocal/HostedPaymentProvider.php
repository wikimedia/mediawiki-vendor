<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class HostedPaymentProvider extends PaymentProvider implements IPaymentProvider {

	protected array $countriesNeedingFiscalNumber = [
		'AR',
		'BR',
	];

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		try {
			// add placeholder params here for countries that need them
			$this->addPlaceholderCreateHostedPaymentParams( $params );
			$this->validateCreateHostedPaymentParams( $params );
			// if recurring pix
			if ( $this->isPixRecurring( $params ) ) {
				$rawResponse = $this->api->pixEnrollment( $params );
			} elseif ( !empty( $params['upi_id'] ) ) {
				// if upi_id exist, means direct UPI bank transfer needs to verify it first
				$rawResponse = $this->api->verifyUpiId( $params );
				// if valid upi, then collect
				if ( $rawResponse['status'] === BankTransferPaymentProvider::UPI_ID_VERIFY_STATUS_VERIFIED ) {
					$rawResponse = $this->api->collectDirectBankTransfer( $params );
				}
			} elseif ( empty( $params['recurring_payment_token'] ) ) {
				$rawResponse = $this->api->redirectHostedPayment( $params );
			} else {
				// subsequent recurring will contain the token
				$rawResponse = $this->api->createPaymentFromToken( $params );
			}
			return DlocalCreatePaymentResponseFactory::fromRawResponse( $rawResponse );
		} catch ( ValidationException $validationException ) {
			$response = new CreatePaymentResponse();
			self::handleValidationException( $response, $validationException->getData() );
			return $response;
		} catch ( ApiException $apiException ) {
			return DlocalCreatePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
	}

	public static function isPixRecurring( array $params ): bool {
		$submethod = $params['payment_submethod'] ?? '';
		$isRecurring = ( !empty( $params['recurring'] ) || !empty( $params['recurring_payment_token'] ) );
		return $isRecurring && $submethod == 'pix';
	}

	/**
	 * Check for the following required params
	 * 'amount'
	 * 'order_id'
	 * 'currency'
	 * 'first_name'
	 * 'last_name'
	 * 'email'
	 * 'fiscal_number'
	 * 'country',
	 * @throws ValidationException
	 */
	private function validateCreateHostedPaymentParams( $params ): void {
		$requiredFields = [
			'amount',
			'currency',
			'country',
			'order_id',
			'first_name',
			'last_name',
			'email',
		];

		if ( !empty( $params['country'] ) && in_array( $params['country'], $this->countriesNeedingFiscalNumber, true ) ) {
			$requiredFields[] = 'fiscal_number';
		}

		self::checkFields( $requiredFields, $params );
	}

}
