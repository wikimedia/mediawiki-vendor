<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\PaymentData\RecurringModel;

class RequestMapper {

	private const INTENT_CAPTURE = 'capture';

	/**
	 * Trustly is currently a capture-only payment method, so we set the 'intent'
	 * flag on Gravy API calls to capture
	 */
	private const CAPTURE_ONLY_PAYMENT_METHOD = [ 'trustly' ];

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
			'external_identifier' => $params['order_id']
		];

		if ( !empty( $params['processor_contact_id'] ) ) {
			$request['buyer_id'] = $params['processor_contact_id'];
		} else {
			$request['buyer'] = [
				'external_identifier' => strtolower( $params['email'] ),
				'billing_details' => [
					'first_name' => $params['first_name'],
					'last_name' => $params['last_name'],
					'email_address' => strtolower( $params['email'] ),
					'phone_number' => $params['phone_number'] ?? null,
					'address' => [
						'city' => $params['city'] ?? null,
						'country' => $params['country'] ?? null,
						'postal_code' => $params['postal_code'] ?? null,
						'state' => $params['state_province'] ?? null,
						'line1' => $params['street_address'] ?? null,
						'line2' => null,
						'organization' => $params['employer'] ?? null
					]
				]
			];
		}

		if ( !empty( $params['recurring'] ) ) {
			$request = $this->addRecurringParams( $params, $request );
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
					'city' => $params['city'] ?? null,
					'country' => $params['country'] ?? null,
					'postal_code' => $params['postal_code'] ?? null,
					'state' => $params['state_province'] ?? null,
					'line1' => $params['street_address'] ?? null,
					'line2' => "",
					'organization' => $params['employer'] ?? null
				]
			]
		];

		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToRedirectCreatePaymentRequest( array $params ): array {
		$request = $this->mapToCreatePaymentRequest( $params );

		$method = $params['payment_submethod'];
		if ( empty( $method ) ) {
			$method = $params['payment_method'];
		}

		if ( !isset( $params['recurring_payment_token'] ) ) {
			$payment_method = [
				'method' => $this->mapPaymentMethodToGravyPaymentMethod( $method ),
				'country' => $params['country'],
				'currency' => $params['currency'],
			];
			$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );
		}

		if ( in_array( $method, self::CAPTURE_ONLY_PAYMENT_METHOD ) ) {
			/**
			 * Defines the intent of a Gravy API call
			 *
			 * Available options:
			 * - `authorize` (Default): Optionally approves and then authorizes a
			 * transaction, but does not capture the funds.
			 * - `capture`: Optionally approves and then authorizes and captures the
			 * funds of the transaction.
			 */
			$request['intent'] = self::INTENT_CAPTURE;
		}
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
		}

		$request['payment_method'] = array_merge( $request['payment_method'], $payment_method );

		return $request;
	}

	/**
	 * @return array
	 */
	public function mapToApprovePaymentRequest( array $params ): array {
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
	 * @param mixed $paymentMethod
	 * @return string
	 */
   private function mapPaymentMethodToGravyPaymentMethod( $paymentMethod ): string {
	   switch ( strtolower( $paymentMethod ) ) {
			case 'ach':
				return 'trustly';
		   case 'paypal':
		   case 'venmo':
				return $paymentMethod;
		   default:
				throw new \UnexpectedValueException( "Unknown Gravy Payment Method - $paymentMethod" );
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

	/**
	 * @param array $params
	 * @param array $request
	 * @return array
	 */
	protected function addRecurringParams( array $params, array $request ): array {
		if ( !$this->isRecurringCharge( $params ) ) {
			$request['store'] = true;
		} else {
			$request['merchant_initiated'] = true;
			$request['is_subsequent_payment'] = true;
			$request['payment_method'] = [
				'method' => 'id',
				'id' => $params['recurring_payment_token'],
			];
		}

		// Default recurring model to 'Subscription' but allow for Card On File
		// in case of speculative tokenization (e.g. for monthly convert).
		$recurringModel = $params['recurring_model'] ?? RecurringModel::SUBSCRIPTION;

		switch ( $recurringModel ) {
			case RecurringModel::SUBSCRIPTION:
				$request['payment_source'] = 'recurring';
				break;
			case RecurringModel::CARD_ON_FILE:
				$request['payment_source'] = 'card_on_file';
				break;
			default:
				throw new \UnexpectedValueException( "Unknown recurring processing model $recurringModel" );
		}

		return $request;
	}

}
