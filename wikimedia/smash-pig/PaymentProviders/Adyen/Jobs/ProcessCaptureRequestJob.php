<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PaymentsFraudDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\RetryableException;
use SmashPig\CrmLink\Messages\DonationInterfaceAntifraudFactory;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\Adyen\CardPaymentProvider;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Job that checks authorization IPN messages from Adyen and requests payment
 * capture if not yet processed and if the risk score is below our threshold.
 *
 * Class ProcessCaptureRequestJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class ProcessCaptureRequestJob extends RunnableJob {

	protected $account;
	protected $currency;
	protected $amount;
	protected $merchantReference;
	protected $shopperReference;
	protected $pspReference;
	protected $avsResult;
	protected $cvvResult;
	/**
	 * @var string Adyen-side code for the card type
	 */
	protected $paymentMethod;
	/**
	 * @var TaggedLogger
	 */
	protected $logger;
	protected $propertiesExcludedFromExport = [ 'logger' ];

	protected $isSuccessfulAutoRescue = false;
	const ACTION_DUPLICATE = 'duplicate'; // duplicate payment attempt - cancel the authorization
	const ACTION_IGNORE = 'ignore'; // duplicate authorisation IPN - ignore
	const ACTION_MISSING = 'missing'; // missing donor details - shunt job to damaged queue

	public static function factory( Authorisation $authMessage ) {
		$obj = new ProcessCaptureRequestJob();

		$obj->account = $authMessage->merchantAccountCode;
		$obj->currency = $authMessage->currency;
		$obj->amount = $authMessage->amount;
		$obj->merchantReference = $authMessage->merchantReference;
		$obj->shopperReference = $authMessage->shopperReference;
		$obj->pspReference = $authMessage->pspReference;
		$obj->cvvResult = $authMessage->cvvResult;
		$obj->avsResult = $authMessage->avsResult;
		$obj->paymentMethod = $authMessage->paymentMethod;
		$obj->isSuccessfulAutoRescue = $authMessage->isSuccessfulAutoRescue();
		return $obj;
	}

	public function execute() {
		$this->logger = Logger::getTaggedLogger( "psp_ref-{$this->pspReference}" );
		$this->logger->info(
			"Running capture request job on account '{$this->account}'" .
			"with reference '{$this->pspReference}'."
		);

		// Determine if a message exists in the pending database; if it does not then
		// this payment has already been sent to the donations queue, or there is a
		// problem with the database. If it does exist, we need to check
		// $capture_requested in case we have requested a capture but have not yet
		// received notification of capture success. Either case can occur when a
		// donor submits their credit card details multiple times against a single
		// order ID. We should cancel duplicate authorizations, but leave payments
		// with missing donor details open for potential manual capture.
		$this->logger->debug( 'Attempting to locate associated message in pending database.' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'adyen', $this->merchantReference );
		$messageIsFromFredge = false;
		if ( !$dbMessage ) {
			$this->logger->info( 'No message found in pending database, looking in fredge.' );
			// Try to find the risk score from fredge
			$messageIsFromFredge = true;
			$fraudDb = PaymentsFraudDatabase::get();
			$dbMessage = $fraudDb->fetchMessageByGatewayOrderId(
				'adyen', $this->merchantReference
			);
		}
		$success = true;

		$action = $this->determineAction( $dbMessage );
		switch ( $action ) {
			case ValidationAction::PROCESS:
				// Attempt to capture the payment
				$provider = $this->getProvider();
				$this->logger->info(
					"Attempting capture API call for currency '{$this->currency}', " .
					"amount '{$this->amount}', reference '{$this->pspReference}'."
				);

				$captureResult = $provider->approvePayment( [
					'gateway_txn_id' => $this->pspReference,
					'currency' => $this->currency,
					'amount' => $this->amount
				] );

				if ( $captureResult->isSuccessful() ) {
					// Success!
					$this->logger->info(
						"Successfully captured payment! Returned reference: '{$captureResult->getGatewayTxnId()}'. " .
							'Marking pending database message as captured.'
					);

					if ( !$messageIsFromFredge ) {
						// If we read the message from pending, update it.
						$dbMessage['captured'] = true;
						$dbMessage['gateway_txn_id'] = $this->pspReference;
						$db->storeMessage( $dbMessage );
					}
				} else {
					// Some kind of error in the request. We should keep the pending
					// db entry, complain loudly, and move this capture job to the
					// damaged queue.
					$this->logger->error(
						"Failed to capture payment on account '{$this->account}' with reference " .
							"'{$this->pspReference}' and order id '{$this->merchantReference}'.",
						$dbMessage
					);
					$success = false;
				}
				break;
			case ValidationAction::REJECT:
				$this->cancelAuthorization();
				// Delete the fraudy donor details
				$db->deleteMessage( $dbMessage );
				break;
			case self::ACTION_DUPLICATE:
				// We have already captured one payment for this donation attempt, so
				// cancel the duplicate authorization. If there is a pending db entry,
				// leave it intact for the legitimate RecordCaptureJob.
				$this->cancelAuthorization();
				break;
			case ValidationAction::REVIEW:
				// Don't capture the payment right now, but leave the donor details in
				// the pending database in case the authorization is captured via the console.
				break;
			case self::ACTION_MISSING:
				// Missing donor details - retry later, unless this is a likely recurring installment
				if ( !$this->isLikelyRecurring() ) {
					throw new RetryableException( 'Missing donor details' );
				}
			case self::ACTION_IGNORE:
				// We got a second Authorisation IPN for the same donation attempt with
				// the exact same PSP reference. Just drop the second one.
				break;
		}

		return $success;
	}

	protected function determineAction( $dbMessage ) {
		if ( $this->isSuccessfulAutoRescue ) {
			 return ValidationAction::PROCESS;
		}
		if ( $dbMessage && isset( $dbMessage['order_id'] ) ) {
			$this->logger->debug( 'Found a valid message.' );
		} else {
			$errMessage = "Could not find a processable message for " .
				"PSP Reference '{$this->pspReference}' and " .
				"order ID '{$this->merchantReference}'.";
			$this->logger->warning(
				$errMessage,
				$dbMessage
			);
			return self::ACTION_MISSING;
		}
		if ( !empty( $dbMessage['captured'] ) ) {
			if ( $this->pspReference === $dbMessage['gateway_txn_id'] ) {
				$this->logger->info(
					"Duplicate Authorisation IPN for PSP reference '{$this->pspReference}' and order ID '{$this->merchantReference}'.",
					$dbMessage
				);
				return self::ACTION_IGNORE;
			} else {
				$this->logger->info(
					"Duplicate PSP Reference '{$this->pspReference}' for order ID '{$this->merchantReference}'.",
					$dbMessage
				);
				return self::ACTION_DUPLICATE;
			}
		}
		return $this->getRiskAction( $dbMessage );
	}

	protected function getRiskAction( $dbMessage ) {
		$providerConfig = Context::get()->getProviderConfiguration();
		$riskScore = isset( $dbMessage['risk_score'] ) ? $dbMessage['risk_score'] : 0;
		$this->logger->debug( "Base risk score from payments site is $riskScore, " .
			"raw CVV result is '{$this->cvvResult}' and raw AVS result is '{$this->avsResult}'." );
		$cvvMap = $providerConfig->val( 'fraud-filters/cvv-map' );
		$avsMap = $providerConfig->val( 'fraud-filters/avs-map' );
		$scoreBreakdown = [];
		if ( array_key_exists( $this->cvvResult, $cvvMap ) ) {
			$scoreBreakdown['getCVVResult'] = $cvvScore = $cvvMap[$this->cvvResult];
			$this->logger->debug( "CVV result '{$this->cvvResult}' adds risk score $cvvScore." );
			$riskScore += $cvvScore;
		} else {
			$this->logger->warning( "CVV result '{$this->cvvResult}' not found in cvv-map.", $cvvMap );
		}
		if ( array_key_exists( $this->avsResult, $avsMap ) ) {
			$scoreBreakdown['getAVSResult'] = $avsScore = $avsMap[$this->avsResult];
			$this->logger->debug( "AVS result '{$this->avsResult}' adds risk score $avsScore." );
			$riskScore += $avsScore;
		} else {
			$this->logger->warning( "AVS result '{$this->avsResult}' not found in avs-map.", $avsMap );
		}
		$action = ValidationAction::PROCESS;
		if ( $riskScore >= $providerConfig->val( 'fraud-filters/review-threshold' ) ) {
			$action = ValidationAction::REVIEW;
		}
		if ( $riskScore >= $providerConfig->val( 'fraud-filters/reject-threshold' ) ) {
			$action = ValidationAction::REJECT;
		}
		$this->sendAntifraudMessage( $dbMessage, $riskScore, $scoreBreakdown, $action );
		return $action;
	}

	protected function sendAntifraudMessage( $dbMessage, $riskScore, $scoreBreakdown, $action ) {
		$antifraudMessage = DonationInterfaceAntifraudFactory::create(
			$dbMessage, $riskScore, $scoreBreakdown, $action
		);
		$this->logger->debug( "Sending antifraud message with risk score $riskScore and action $action." );
		QueueWrapper::push( 'payments-antifraud', $antifraudMessage );
	}

	/**
	 * @return CardPaymentProvider
	 */
	protected function getProvider() {
		return PaymentProviderFactory::getProviderForMethod( 'cc' );
	}

	protected function cancelAuthorization() {
		$this->logger->debug( "Cancelling authorization with reference '{$this->pspReference}'" );
		$provider = $this->getProvider();
		$result = $provider->cancelPayment( $this->pspReference );
		if ( $result->isSuccessful() ) {
			$this->logger->debug( "Successfully cancelled authorization" );
		} else {
			// Not a big deal
			$this->logger->warning( "Failed to cancel authorization, it will remain in the payment console" );
		}
	}

	/**
	 * Amex and Discover recurring installment IPNs don't come in with all the
	 * markings we expect. If the payment method is one of those and the
	 * sequential bit of the merchant ref is greater than one, we can probably
	 * skip sending a failmail when there are no donor details in the queue.
	 */
	protected function isLikelyRecurring() {
		$merchantReferenceParts = explode( '.', $this->merchantReference );
		$sequenceNumber = (int)$merchantReferenceParts[1];
		return (
			in_array(
				$this->paymentMethod,
				[ 'amex', 'discover' ],
				true
			) &&
			$sequenceNumber > 1
		);
	}
}
