<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class RequestMapper {
	private const CAPTURE_INTENT = 'capture';

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
			if ( !$this->isRecurringCharge( $params ) ) {
				$request['store'] = true;
			} else {
				$request['merchant_initiated'] = true;
				$request['is_subsequent_payment'] = true;
			}
			$request['payment_source'] = 'recurring';
		}

		if ( !empty( $params['return_url'] ) ) {
			$request['payment_method']['redirect_url'] = $params['return_url'];
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
	public function mapToBankCreatePaymentRequest( array $params ): array {
		$request = $this->mapToCreatePaymentRequest( $params );
		if ( isset( $params['recurring_payment_token'] ) ) {
			$payment_method = [
				'method' => 'id',
				'id' => $params['recurring_payment_token'],
			];
		} else {
			$payment_method = [
				'method' => $this->mapPaymentMethodToGravyPaymentMethod( $params['payment_submethod'] ),
				'country' => $params['country'],
				'currency' => $params['currency'],
			];
		}
		$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );

		$request['intent'] = self::CAPTURE_INTENT;
		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToCardCreatePaymentRequest( array $params ): array {
		$request = $this->mapToCreatePaymentRequest( $params );
		$payment_method = [];
		if ( isset( $params['gateway_session_id'] ) ) {
			$payment_method = [
				'method' => 'checkout-session',
				'id' => $params['gateway_session_id'],
			];
		} elseif ( isset( $params['recurring_payment_token'] ) ) {
			$payment_method = [
				'method' => 'id',
				'id' => $params['recurring_payment_token'],
			];
		}
		$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );

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

	/**
	 * Check if payment params is for recurring charge
	 * @param array $params
	 * @return bool
	 */
	private function isRecurringCharge( array $params ): bool {
		return isset( $params['recurring_payment_token'] );
	}

	/**
	 * Maps our payment submethod to gravy's
	 * @param mixed $payment_submethod
	 * @return string
	 */
   private function mapPaymentMethodToGravyPaymentMethod( $payment_submethod ): string {
	   switch ( $payment_submethod ) {
		   case 'ach':
			   return 'trustly';
		   default:
			   return '';
	   }
   }

	/**
	 * @return array
	 */
	public function mapToRefundPaymentRequest( array $params ): array {
		$body = [
			"reason" => $params["reason"] ?? "Refunded due to user request",
		];

		if ( isset( $params['amount'] ) && !empty( $params['amount'] ) ) {
			$body["amount"] = CurrencyRoundingHelper::getAmountInMinorUnits( $params['amount'], $params['currency'] );
		}

		$request = [
			'gateway_txn_id' => $params['gateway_txn_id'],
			'body' => $body
		];
		return $request;
	}

}
