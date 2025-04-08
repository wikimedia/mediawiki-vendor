<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentProviders\Gravy\CountryIdentifiers;

class RequestMapper {

	public const INTENT_CAPTURE = 'capture';

	/**
	 * List for payment methods that do not have the 2 step auth/capture
	 */
	public const CAPTURE_ONLY_PAYMENT_METHOD = [
		'netbanking',
		'pix',
		'oxxo',
		'pse',
		'bcp',
		'webpay',
		'pagoefectivo',
		'redpagos',
		'abitab',
		'boleto',
		'stitch'
	];

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
					'phone_number' => $params['phone'] ?? null,
					'address' => [
						'city' => $params['city'] ?? null,
						'country' => $params['country'] ?? null,
						'postal_code' => $params['postal_code'] ?? null,
						'state' => $params['state_province'] ?? null,
						'line1' => $params['street_address'] ?? null,
						'line2' => null,
						'organization' => $params['employer'] ?? null,
					]
				]
			];

			if ( !empty( $params['fiscal_number'] ) ) {
				$request = $this->addFiscalNumberParams( $params, $request );
			}

			if ( !empty( $params['street_number'] ) ) {
				$request['buyer']['billing_details']['address']['house_number_or_name'] = $params['street_number'];
			}
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
	 * @return array
	 */
	public function mapToRefundPaymentRequest( array $params ): array {
		$body = [
			'reason' => $params['reason'] ?? 'Refunded due to user request',
		];

		if ( !empty( $params['amount'] ) ) {
			$body['amount'] = CurrencyRoundingHelper::getAmountInMinorUnits( $params['amount'], $params['currency'] );
		}

		$request = [
			'gateway_txn_id' => $params['gateway_txn_id'],
			'body' => $body
		];
		return $request;
	}

	public function mapToAppleCreatePaymentRequest( array $params ): array {
		$request_params = $this->mapToCreatePaymentRequest( $params );
		$request_params['payment_method'] = array_merge( $request_params['payment_method'], [
			'method' => 'applepay',
			'token' => json_decode( $params['payment_token'] ),
		] );
		return $request_params;
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

	/**
	 * Check if payment params is for recurring charge
	 * @param array $params
	 * @return bool
	 */
	protected function isRecurringCharge( array $params ): bool {
		return isset( $params['recurring_payment_token'] );
	}

	/**
	 * Add country-specific identifier to the request where applicable.
	 *
	 * NOTE: Gravy groups all the personal identifiers under the label of tax_id, which might be misleading in the future
	 * as they are not all tax-related.
	 *
	 * @param array $params
	 * @param array $request
	 * @return array
	 */
	protected function addFiscalNumberParams( array $params, array $request ): array {
		$identifier = CountryIdentifiers::getGravyTaxIdTypeForSuppliedCountryIdentifier( $params['country'], $params['fiscal_number'] );
		if ( $identifier ) {
			$request['buyer']['billing_details']['tax_id'] = [
				'value' => strval( $params['fiscal_number'] ),
				'kind' => $identifier
			];
		} else {
			throw new \UnexpectedValueException( "Can't map fiscal number to Gravy Tax ID type.  ({$params['country']}:{$params['fiscal_number']})" );
		}
		return $request;
	}
}
