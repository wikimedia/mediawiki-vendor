<?php

namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PaymentsFraudDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Runnable;
use SmashPig\CrmLink\Messages\DonationInterfaceAntifraudFactory;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\GravyMessage;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetLatestPaymentStatusResponseFactory;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

/**
 * Job that sends a Transaction Webhook message from Gravy into the donations queue.
 *
 * Class TransactionMessageJob
 *
 * @package SmashPig\PaymentProviders\Gravy\Jobs
 */
class ProcessCaptureRequestJob implements Runnable {

	public array $payload;

	protected TaggedLogger $logger;

	const ACTION_DUPLICATE = 'duplicate'; // duplicate payment attempt - cancel the authorization
	const ACTION_IGNORE = 'ignore'; // duplicate authorisation IPN - ignore
	const ACTION_MISSING = 'missing'; // missing donor details - shunt job to damaged queue

	public static function factory( GravyMessage $message, PaymentDetailResponse $transactionDetails ): array {
		return [
			'class' => self::class,
			'payload' => array_merge(
				[
					'eventDate' => $message->getMessageDate()
				], $transactionDetails->getNormalizedResponse()
			)
		];
	}

	public function execute() {
		$transactionDetails = GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse( $this->payload );
		$this->logger = Logger::getTaggedLogger( "psp_ref-{$transactionDetails->getGatewayTxnId()}" );
		$this->logger->info(
			'Running capture request job on Gravy transaction' .
			" with reference '{$transactionDetails->getGatewayTxnId()}'."
		);

		$this->logger->debug( 'Attempting to locate associated message in pending database.' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'gravy', $transactionDetails->getOrderId() );
		$messageIsFromFredge = false;
		if ( !$dbMessage ) {
			$this->logger->info( 'No message found in pending database, looking in fredge.' );
			// Try to find the risk score from fredge
			$messageIsFromFredge = true;
			$fraudDb = PaymentsFraudDatabase::get();
			$dbMessage = $fraudDb->fetchMessageByGatewayOrderId(
				'gravy', $transactionDetails->getOrderId()
			);
		}
		$success = true;
		$action = $this->determineAction( $dbMessage, $transactionDetails );

		switch ( $action ) {
			case ValidationAction::PROCESS:
				// Attempt to capture the payment
				$provider = $this->getProvider();

				$this->logger->info(
					"Attempting capture API call for currency '{$transactionDetails->getCurrency()}', " .
					"amount '{$transactionDetails->getAmount()}', reference '{$transactionDetails->getGatewayTxnId()}'."
				);
				$captureResult = $provider->approvePayment( [
					'gateway_txn_id' => $transactionDetails->getGatewayTxnId(),
					'currency' => $transactionDetails->getCurrency(),
					'amount' => $transactionDetails->getAmount()
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
						$dbMessage['gateway_txn_id'] = $transactionDetails->getGatewayTxnId();
						$db->storeMessage( $dbMessage );
					}
				} else {
					// Some kind of error in the request. We should keep the pending
					// db entry, complain loudly, and move this capture job to the
					// damaged queue.
					$this->logger->error(
						"Failed to capture gravy payment with reference " .
							"'{$transactionDetails->getGatewayTxnId()}' and order id '{$transactionDetails->getOrderId()}'.",
						$dbMessage
					);
					$success = false;
				}
				break;
			case ValidationAction::REJECT:
				$this->cancelAuthorization( $transactionDetails );
				// Delete the fraudy donor details
				$db->deleteMessage( $dbMessage );
				break;
			case self::ACTION_DUPLICATE:
				// We have already captured one payment for this donation attempt, so
				// cancel the duplicate authorization. If there is a pending db entry,
				// leave it intact for the legitimate RecordCaptureJob.
				$this->cancelAuthorization( $transactionDetails );
				break;
			case ValidationAction::REVIEW:
				// Don't capture the payment right now, but leave the donor details in
				// the pending database in case the authorization is captured via the console.
				break;
			case self::ACTION_MISSING:
			case self::ACTION_IGNORE:
				// We got a second Authorisation IPN for the same donation attempt with
				// the exact same Gateway transaction ID. Just drop the second one.
				break;
		}

		return $success;
	}

	protected function getRiskAction( array $dbMessage, PaymentDetailResponse $transactionDetails ): string {
		$providerConfig = Context::get()->getProviderConfiguration();
		$riskScore = isset( $dbMessage['risk_score'] ) ? $dbMessage['risk_score'] : 0;
		$riskScores = $transactionDetails->getRiskScores();
		$cvvScore = isset( $riskScores['cvv'] ) ? $riskScores['cvv'] : 0;
		$avsScore = isset( $riskScores['avs'] ) ? $riskScores['avs'] : 0;

		$this->logger->debug( "Base risk score from payments site is $riskScore, " .
			"CVV score is '{$cvvScore}' and AVS score is '{$avsScore}'." );

		$riskScore += $cvvScore + $avsScore;
		$scoreBreakdown = [
			'getCVVResult' => $cvvScore,
			'getAVSResult' => $avsScore
		];

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
	protected function getProvider(): CardPaymentProvider {
		return PaymentProviderFactory::getProviderForMethod( 'cc' );
	}

	protected function determineAction( $dbMessage, PaymentDetailResponse $transactionDetails ): string {
		if ( $dbMessage && isset( $dbMessage['order_id'] ) ) {
			$this->logger->debug( 'Found a valid message.' );
		} else {
			$errMessage = "Could not find a processable message for " .
				"Gateway transaction ID '{$transactionDetails->getGatewayTxnId()}' and " .
				"order ID '{$transactionDetails->getOrderId()}'.";
			$this->logger->warning(
				$errMessage,
				$dbMessage
			);
			return self::ACTION_MISSING;
		}
		if ( !empty( $dbMessage['captured'] ) ) {
			if ( $transactionDetails->getGatewayTxnId() === $dbMessage['gateway_txn_id'] ) {
				$this->logger->info(
					"Duplicate Authorisation IPN for Gateway transaction ID '{$transactionDetails->getGatewayTxnId()}' and order ID '{$transactionDetails->getOrderId()}'.",
					$dbMessage
				);
				return self::ACTION_IGNORE;
			} else {
				$this->logger->info(
					"Duplicate Gateway transaction ID '{$transactionDetails->getGatewayTxnId()}' for order ID '{$transactionDetails->getOrderId()}'.",
					$dbMessage
				);
				return self::ACTION_DUPLICATE;
			}
		}
		return $this->getRiskAction( $dbMessage, $transactionDetails );
	}

	protected function cancelAuthorization( PaymentDetailResponse $transactionDetails ) {
		$this->logger->debug( "Cancelling authorization with reference '{$transactionDetails->getGatewayTxnId()}'" );
		$provider = $this->getProvider();
		$result = $provider->cancelPayment( $transactionDetails->getGatewayTxnId() );
		if ( $result->isSuccessful() ) {
			$this->logger->debug( 'Successfully cancelled authorization' );
		} else {
			// Not a big deal
			$this->logger->warning( 'Failed to cancel authorization, it will remain in the payment console' );
		}
	}
}
