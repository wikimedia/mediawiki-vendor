<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class HostedPaymentProvider extends PaymentProvider implements IPaymentProvider {

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
			// if upi_id exist, means direct UPI bank transfer needs to verify it first
			if ( !empty( $params['upi_id'] ) ) {
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
			'fiscal_number',
		];

		self::checkFields( $requiredFields, $params );
	}

	/**
	 * Adds default params when none were entered
	 * Currently focused on fiscal number
	 * This is copied from PlaceholderFiscalNumber.php in Donation Interface
	 *
	 * TODO: Find this a better place to live (maybe)
	 *
	 */
	private function addPlaceholderCreateHostedPaymentParams( &$params ): void {
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
	}

}
