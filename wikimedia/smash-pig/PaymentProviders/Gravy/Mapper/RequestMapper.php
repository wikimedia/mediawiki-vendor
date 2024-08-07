<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class RequestMapper {

	public function mapToCreatePaymentRequest( array $params ): array {
		$request = [
			// Gravy requires amount to be sent in the smallest unit for the given currency
			// See https://docs.gr4vy.com/reference/transactions/new-transaction
			'amount' => CurrencyRoundingHelper::getAmountInMinorUnits( $params['amount'], $params['currency'] ),
			'currency' => $params['currency'],
			'country' => $params['country'],
			'payment_method' => [
				'method' => $params['method'] ?? '',
			],
			'external_identifier' => $params['order_id'],
		];

		if ( !empty( $params['processor_contact_id'] ) ) {
			$request['buyer_id'] = $params['processor_contact_id'];
		}

		if ( !empty( $params['recurring'] ) ) {
			$request['store'] = true;
			$request['payment_source'] = 'recurring';
		}

		if ( !empty( $params['redirect_url'] ) ) {
			$request['payment_method']['redirect_url'] = $params['redirect_url'];
		}

		return $request;
	}

	/**
	 * @param array $params
	 * @return array
	 * T370700 Gravy treat buyer email case-sensitive
	 */
	public function mapToGetDonorRequest( array $params ): array {
		$request = [
			'external_identifier' => strtolower( $params['email'] )
		];

		return $request;
	}

	/**
	 * @param array $params
	 * @return array
	 * T370700 Make sure buyer email all lowercase to avoid duplicate buyer creation in gravy
	 */
	public function mapToCreateDonorRequest( array $params ): array {
		$request = [
			'display_name' => $params['first_name'] . ' ' . $params['last_name'],
			'external_identifier' => strtolower( $params['email'] ),
			'billing_details' => [
				'first_name' => $params['first_name'],
				'last_name' => $params['last_name'],
				'email_address' => strtolower( $params['email'] ),
				'phone_number' => $params['phone_number'] ?? null,
				'address' => [
					'city' => $params['city'] ?? " ",
					'country' => $params['country'] ?? " ",
					'postal_code' => $params['postal_code'] ?? " ",
					'state' => $params['state_province'] ?? " ",
					'line1' => $params['street_address'] ?? " ",
					'line2' => " ",
					'organization' => $params['employer'] ?? " "
				]
			]
		];

		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToCardCreatePaymentRequest( array $params ): array {
		$request = $this->mapToCreatePaymentRequest( $params );

		$request['payment_method'] = array_merge( $request['payment_method'], [
			'method' => 'checkout-session',
			'id' => $params['gateway_session_id'],
		] );

		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToCardApprovePaymentRequest( array $params ): array {
		$request = [
			'amount' => CurrencyRoundingHelper::getAmountInMinorUnits( $params['amount'], $params['currency'] ),
		];
		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToDeletePaymentTokenRequest( array $params ): array {
		$request = [
			'payment_method_id' => $params['recurring_payment_token'],
		];
		return $request;
	}

}
