<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class RequestMapper {

	public function mapToCreatePaymentRequest( array $params ): array {
		$request = [
			'amount' => $this->convertAmountToGravyAmountFormat( $params['amount'] ),
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

	public function mapToGetDonorRequest( array $params ): array {
		$request = [
			'external_identifier' => $params['email']
		];

		return $request;
	}

	public function mapToCreateDonorRequest( array $params ): array {
		$request = [
			'display_name' => $params['first_name'] . ' ' . $params['last_name'],
			'external_identifier' => $params['email'],
			'billing_details' => [
				'first_name' => $params['first_name'],
				'last_name' => $params['last_name'],
				'email_address' => $params['email'],
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
			'amount' => $this->convertAmountToGravyAmountFormat( $params['amount'] ),
		];
		return $request;
	}

	/**
	 * Gravy requires amounts to be sent over in cents.
	 *
	 * @see https://docs.gr4vy.com/reference/transactions/new-transaction
	 * @param string $amount
	 * @return float
	 */
	protected function convertAmountToGravyAmountFormat( string $amount ): float {
		return (float)$amount * 100;
	}

}
