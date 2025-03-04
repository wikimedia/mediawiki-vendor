<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction;

class Authorisation extends AdyenMessage {

	/** @var string The payment method used in this transaction, eg visa, mc, ideal, ev, wallie, etc... */
	public $paymentMethod = '';

	/** @var array Modification operations currently supported by the referenced transaction. This includes
	 * things like CAPTURE, REFUND, and CANCEL.
	 */
	public $operations = [];

	/** @var string When success is set to true and the payment method is visa, mc, or amex this field contains
	 * the authorization code, last 4 digits of the card, and the expiry date as <6 digit auth>:<Last 4>:<MM/YYYY>.
	 * When success is false, this is a string describing the refusal reason.
	 */
	public $reason = '';

	public $cvvResult = '';
	public $avsResult = '';
	public $recurringProcessingModel = '';
	public $recurringDetailReference = '';
	public $shopperReference = '';
	public $retryRescueScheduled = false;
	public $retryRescueReference = '';
	public $retryOrderAttemptNumber = 0;
	public $retryNextAttemptDate = '';

	/**
	 * Overloads the generic Adyen method adding fields specific to the Authorization message
	 * type.
	 *
	 * @param array $notification
	 */
	protected function constructFromJSON( array $notification ) {
		parent::constructFromJSON( $notification );

		$this->paymentMethod = $notification['paymentMethod'] ?? null; // AutoRescue messages inherit this method and do not have a paymethod property.

		// Add AVS, CVV results, recurringProcessingModel, and recurringDetailReference from additionalData if any is provided
		if ( empty( $notification['additionalData'] ) || !is_array( $notification['additionalData'] ) ) {
			return;
		}

		// For AVS and CVV we just want the code, not the words
		$firstSegment = function ( $value ): string {
			$parts = explode( ' ', $value );
			return $parts[0];
		};

		foreach ( $notification['additionalData'] as $key => $value ) {
			switch ( $key ) {
				case 'cvcResult':
					$this->cvvResult = $firstSegment( $value );
					break;
				case 'avsResult':
					$this->avsResult = $firstSegment( $value );
					break;
				case 'recurringProcessingModel':
					$this->recurringProcessingModel = $value;
					break;
				case 'recurring.recurringDetailReference':
					$this->recurringDetailReference = $value;
					break;
				case 'recurring.shopperReference':
					$this->shopperReference = $value;
					break;
				case 'retry.rescueScheduled':
					$this->retryRescueScheduled = $value;
					break;
				case 'retry.rescueReference':
					$this->retryRescueReference = $value;
					break;
				case 'retry.orderAttemptNumber':
					$this->retryOrderAttemptNumber = $value;
					break;
				case 'retry.nextAttemptDate':
					$this->retryNextAttemptDate = $value;
					break;
			}
		}
	}

	/**
	 * Will run all the actions that are loaded (from the 'actions' configuration
	 * node) and that are applicable to this message type. Will return true
	 * if all actions returned true. Otherwise will return false. This implicitly
	 * means that the message will be re-queued if any action fails. Therefore
	 * all actions need to be idempotent.
	 *
	 * @return bool True if all actions were successful. False otherwise.
	 */
	public function runActionChain() {
		$action = new PaymentCaptureAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}

	/**
	 * Check if message is a successful auto rescue payment
	 *
	 * @return bool True if successful auto rescue
	 */
	public function isSuccessfulAutoRescue(): bool {
		if ( $this->success && $this->retryOrderAttemptNumber > 0 ) {
			return true;
		}
		return false;
	}

	/**
	 * Only capture Authorisation AutoRescue message
	 *
	 * @return bool
	 */
	public function processAutoRescueCapture(): bool {
		return true;
	}

	/**
	 * Check either retryRescueScheduled false, which indicated no more rescue schedule:
	 * https://docs.adyen.com/online-payments/auto-rescue/cards/#rescue-process-ended
	 * Or if end auto rescue webhook send with below reasons:
	 * https://docs.adyen.com/online-payments/auto-rescue/cards/#rescue-process-ended
	 *
	 * @return bool True if indicate end auto rescue
	 */
	public function isEndedAutoRescue(): bool {
		$autoRetryRefusalReasons = [
			'retryWindowHasElapsed',
			'maxRetryAttemptsReached',
			'fraudDecline',
			'internalError'
		];
		if (
			!empty( $this->retryRescueReference ) &&
			!$this->success && (
				$this->retryRescueScheduled === 'false' ||
				in_array( $this->reason, $autoRetryRefusalReasons, true )
			)
		) {
			return true;
		}
		return false;
	}

	/**
	 * Check for subsequent recurring payment IPNs.
	 *
	 * Credit card recurring payments will not have the recurringDetailReference set
	 * Sepa direct debit recurring payments will have sepadirectdebit as payment method
	 *
	 * @return bool True if it is a recurring payment otherwise False
	 */
	public function isRecurringInstallment(): bool {
		// Check for credit card recurring
		if ( isset( $this->recurringProcessingModel )
				 && $this->recurringProcessingModel == 'Subscription'
				 && $this->recurringDetailReference == '' ) {
			return true;
		}
		// If the shopper reference is set and is not the same as the
		// merchant reference for the current payment, this is recurring.
		if ( $this->shopperReference !== ''
			&& $this->shopperReference !== $this->merchantReference
		) {
			return true;
		}
		// Check for sepa direct debit recurring
		if ( $this->paymentMethod == 'sepadirectdebit' ) {
			return true;
		}
		return false;
	}
}
