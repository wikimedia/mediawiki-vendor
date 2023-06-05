<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class BankTransferPaymentProvider extends HostedPaymentProvider {

	/**
	 * @var string
	 */
	public const SUBSCRIPTION_FREQUENCY_UNIT_ONDEMAND = 'ONDEMAND';
	/**
	 * @var string
	 */
	public const SUBSCRIPTION_FREQUENCY_UNIT_MONTHLY = 'MONTH';
	/**
	 * @var string
	 */
	public const UPI_ID_VERIFY_STATUS_VERIFIED = 'VERIFIED';

	/**
	 * Should be set to one of the two SUBSCRIPTION_FREQUENCY_UNIT_* values
	 * 'ONDEMAND' has fewer limitations for prenotify compared with 'MONTH'
	 * (allows sending a retry in the same month, since needs 2 days to process).
	 * HostedPaymentApiRequestMapper uses ONDEMAND by default if not set.
	 * @var string|null
	 */
	protected $indiaRecurringSubscriptionFrequency;

	/**
	 * @var int|null
	 */
	protected $indiaRecurringSubscriptionDurationInMonths;
	/**
	 * @var bool|null
	 */
	protected $codiUseQrCode;

	public function __construct( ?array $params = null ) {
		parent::__construct();
		if ( !empty( $params['inr_subscription_frequency'] ) ) {
			$this->indiaRecurringSubscriptionFrequency = $params['inr_subscription_frequency'];
		}
		if ( !empty( $params['inr_subscription_months'] ) ) {
			$this->indiaRecurringSubscriptionDurationInMonths = $params['inr_subscription_months'];
		}
		if ( !empty( $params['codi_use_qr_code'] ) ) {
			$this->codiUseQrCode = $params['codi_use_qr_code'];
		}
	}

	public static function isIndiaRecurring( array $params ): bool {
		$submethod = $params['payment_submethod'] ?? '';
		$isRecurring = ( !empty( $params['recurring'] ) || !empty( $params['recurring_payment_token'] ) );
		return $isRecurring && in_array( $submethod, [ 'upi', 'paytmwallet' ] );
	}

	/**
	 * If mx use codi
	 *
	 * @param array $params
	 * @return bool
	 */
	public static function isCodi( array $params ): bool {
		$submethod = $params['payment_submethod'] ?? '';
		return $submethod === 'codi';
	}

	/**
	 * In HostedPaymentApiRequestMapper we default to ONDEMAND if the frequency is not
	 * explicitly set to MONTH in the constructor of this class.
	 *
	 * @return bool
	 */
	public function isUpiSubscriptionFrequencyMonthly() {
		return $this->indiaRecurringSubscriptionFrequency == self::SUBSCRIPTION_FREQUENCY_UNIT_MONTHLY;
	}

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		if ( self::isIndiaRecurring( $params ) ) {
			$params['inr_subscription_frequency'] = $this->indiaRecurringSubscriptionFrequency;
			$params['inr_subscription_months'] = $this->indiaRecurringSubscriptionDurationInMonths;
		}
		if ( self::isCodi( $params ) ) {
			$params['codi_use_qr_code'] = $this->codiUseQrCode;
		}
		return parent::createPayment( $params );
	}
}
