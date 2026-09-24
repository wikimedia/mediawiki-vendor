<?php
namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\RetryableException;
use SmashPig\Core\Runnable;
use SmashPig\Core\SequenceGenerators\Factory as SequenceGeneratorFactory;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\GravyMessage;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetLatestPaymentStatusResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

/**
 * Job that sends a Transaction Webhook message from Gravy into the donations queue.
 *
 * Class TransactionMessageJob
 *
 * @package SmashPig\PaymentProviders\Gravy\Jobs
 */
class RecordCaptureJob implements Runnable {

	public array $payload;

	public static function factory( GravyMessage $message, PaymentProviderExtendedResponse $transactionDetails ): array {
		return [
			'class' => self::class,
			'payload' => array_merge(
				[ 'eventDate' => $message->getMessageDate() ],
				$transactionDetails->getNormalizedResponse()
			)
		];
	}

	public function execute(): bool {
		/** @var PaymentProviderExtendedResponse $transactionDetails */
		$transactionDetails = GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse( $this->payload );
		$logger = Logger::getTaggedLogger( "corr_id-gravy-{$transactionDetails->getOrderId()}" );
		$logger->info(
			'Processing captured Gravy payment with authorization reference ' .
				"'{$transactionDetails->getGatewayTxnId()}' and order ID '{$transactionDetails->getOrderId()}'."
		);

		if ( !empty( $this->payload['is_moto'] ) ) {
			return $this->recordMotoDonation( $transactionDetails, $logger );
		}

		// Find the details from the payment site in the pending database.
		$logger->debug( 'Attempting to locate associated message in pending database' );

		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId(
			'gravy',
			$transactionDetails->getOrderId(),
			$transactionDetails->getGatewayTxnId(),
			'backend_processor_txn_id'
		);

		if ( $dbMessage && ( isset( $dbMessage['order_id'] ) ) ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			$this->addMissingFieldsToPendingRecord( $dbMessage, $transactionDetails );

			// Use the eventDate from the capture as the date
			$dbMessage['date'] = strtotime( $this->payload['eventDate'] );

			QueueWrapper::push( 'donations', $dbMessage );

			// Remove it from the pending database
			$logger->debug( 'Removing donor details message from pending database' );
			$db->markMessageResolved( $dbMessage );

		} else {
			$logger->warning(
				"Could not find donor details for authorization Reference '{$transactionDetails->getGatewayTxnId()}' " .
					"and order ID '{$transactionDetails->getOrderId()}'.",
				$dbMessage
			);
		}

		return true;
	}

	/**
	 * Sends a moto donation to the donations queue.
	 *
	 * Moto gifts are keyed in by staff and uploaded through Gravy rather than
	 * made on a donation form, so there is no pending database record holding
	 * the donor details. The donor is identified instead by the CiviCRM contact
	 * ID carried in the transaction metadata.
	 *
	 * @param PaymentProviderExtendedResponse $partialTransactionDetails
	 * @param TaggedLogger $logger
	 * @return bool
	 */
	protected function recordMotoDonation(
		PaymentProviderExtendedResponse $partialTransactionDetails,
		TaggedLogger $logger
	): bool {
		$logger->debug( 'Moto transaction - skipping the pending database lookup' );

		$transactionDetails = $this->getFullTransactionDetails( $partialTransactionDetails->getGatewayTxnId() );

		// Unlike the pending path we have no donor record to fall back on, so a
		// failed lookup would leave us pushing a donation with no processor
		// details at all. Requeue instead of recording a half-built donation.
		if ( !$transactionDetails->isSuccessful() ) {
			throw new RetryableException(
				'Could not fetch transaction details for moto donation with authorization ' .
					"reference '{$partialTransactionDetails->getGatewayTxnId()}' and order ID " .
					"'{$partialTransactionDetails->getOrderId()}'. Requeuing job."
			);
		}

		$motoMetadata = $this->payload['moto_metadata'] ?? [];

		$donationMessage = [
			'gateway' => 'gravy',
			'gateway_txn_id' => $partialTransactionDetails->getGatewayTxnId(),
			'order_id' => $partialTransactionDetails->getOrderId(),
			'contribution_tracking_id' => $this->generateContributionTrackingId(),
			'contact_id' => $motoMetadata['cid'] ?? null,
			'date' => strtotime( $this->payload['eventDate'] ),
			'gross' => $partialTransactionDetails->getAmount(),
			'currency' => $partialTransactionDetails->getCurrency(),
			'payment_method' => $this->payload['payment_method'] ?? null,
			'payment_submethod' => $transactionDetails->getPaymentSubmethod(),
			'backend_processor' => $transactionDetails->getBackendProcessor(),
			'backend_processor_txn_id' => $transactionDetails->getBackendProcessorTransactionId()
				?: $partialTransactionDetails->getBackendProcessorTransactionId(),
			'payment_orchestrator_reconciliation_id' => $transactionDetails->getPaymentOrchestratorReconciliationId(),
			'payment_service_id' => $transactionDetails->getPaymentServiceID(),
		];

		// Gift details from the transaction metadata, which a donation form would
		// otherwise have supplied. Empty values are dropped rather than sent as
		// nulls, so that CiviCRM falls back to its own defaults rather than
		// storing a blank.
		$donationMessage += array_filter( [
			'direct_mail_appeal' => $motoMetadata['appeal'] ?? null,
			'restrictions' => $motoMetadata['fund'] ?? null,
			'channel' => $motoMetadata['channel'] ?? null,
			'Gift_Data.Package' => $motoMetadata['package'] ?? null,
		] );

		$logger->debug(
			"Pushing moto donation for contact '{$donationMessage['contact_id']}' with " .
				"contribution tracking ID '{$donationMessage['contribution_tracking_id']}'."
		);

		QueueWrapper::push( 'donations', $donationMessage );

		return true;
	}

	/**
	 * Mints a new contribution tracking ID.
	 *
	 * Donations made on a payments form are given one by DonationInterface
	 * before the payment is attempted. Moto gifts never touch a form, so there
	 * is nothing upstream to take the ID from and we mint it here instead.
	 *
	 * @return string
	 */
	protected function generateContributionTrackingId(): string {
		$generator = SequenceGeneratorFactory::getSequenceGenerator( 'contribution-tracking' );

		return (string)$generator->getNext();
	}

	protected function addMissingFieldsToPendingRecord( array &$dbMessage, PaymentProviderExtendedResponse $partialTransactionDetails ): void {
		// Add the gateway transaction ID
		$dbMessage['gateway_txn_id'] = $partialTransactionDetails->getGatewayTxnId();

		$transactionDetails = $this->getFullTransactionDetails( $partialTransactionDetails->getGatewayTxnId() );

		// Other things that are missing for e.g. 3d-secure transactions
		if ( empty( $dbMessage['backend_processor'] ) ) {
			$dbMessage['backend_processor'] = $transactionDetails->getBackendProcessor();
		}
		if ( empty( $dbMessage['payment_submethod'] ) ) {
			$dbMessage['payment_submethod'] = $transactionDetails->getPaymentSubmethod();
		}

		// Always use fresh value - Gravy updates payment_service_transaction_id
		// to payment_service_capture_id (Adyen's pspReference) after capture
		if ( $transactionDetails->getBackendProcessorTransactionId() ) {
			$dbMessage['backend_processor_txn_id'] = $transactionDetails->getBackendProcessorTransactionId();
		}

		if ( empty( $dbMessage['payment_orchestrator_reconciliation_id'] ) ) {
			$dbMessage['payment_orchestrator_reconciliation_id'] = $transactionDetails->getPaymentOrchestratorReconciliationId();
		}
		if ( empty( $dbMessage['payment_service_id'] ) ) {
			$dbMessage['payment_service_id'] = $transactionDetails->getPaymentServiceID();
		}

		$donorDetails = $transactionDetails->getDonorDetails();
		if ( $donorDetails ) {
			if ( empty( $dbMessage['first_name'] ) ) {
				$dbMessage['first_name'] = $donorDetails->getFirstName();
			}
			if ( empty( $dbMessage['last_name'] ) ) {
				$dbMessage['last_name'] = $donorDetails->getLastName();
			}
			if ( empty( $dbMessage['email'] ) ) {
				$dbMessage['email'] = $donorDetails->getEmail();
			}
			$billingAddress = $donorDetails->getBillingAddress();
			if ( $billingAddress ) {
				if ( empty( $dbMessage['city'] ) ) {
					$dbMessage['city'] = $billingAddress->getCity();
				}
				if ( empty( $dbMessage['country'] ) ) {
					$dbMessage['country'] = $billingAddress->getCountryCode();
				}
				if ( empty( $dbMessage['postal_code'] ) ) {
					$dbMessage['postal_code'] = $billingAddress->getPostalCode();
				}
				if ( empty( $dbMessage['state_province'] ) ) {
					$dbMessage['state_province'] = $billingAddress->getStateOrProvinceCode();
				}
				if ( empty( $dbMessage['street_address'] ) ) {
					$dbMessage['street_address'] = $billingAddress->getStreetAddress();
				}
			}
		}

		// Special handling for recurring donations
		if ( !empty( $dbMessage['recurring'] ) ) {
			if ( empty( $dbMessage['recurring_payment_token'] ) ) {
				if ( !empty( $transactionDetails->getRecurringPaymentToken() ) ) {
					$dbMessage['recurring_payment_token'] = $transactionDetails->getRecurringPaymentToken();
				} else {
					throw new RetryableException(
						'Recurring message was obtained from the pending queue with no token. Requeuing job.'
					);
				}
			}
		}
	}

	public function getFullTransactionDetails( string $gateway_txn_id ): PaymentProviderExtendedResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$transactionDetails = $provider->getLatestPaymentStatus( [
			'gateway_txn_id' => $gateway_txn_id,
		] );

		return $transactionDetails;
	}
}
