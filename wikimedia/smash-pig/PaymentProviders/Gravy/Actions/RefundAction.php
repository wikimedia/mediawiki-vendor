<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\MailHandler;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\RefundMessage;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class RefundAction extends GravyAction {
	use RefundTrait;

	const ERROR_CODE_REFUND_ALREADY_SATISFIED = 'refund_already_satisfied';

	private const ERROR_CODE_UNEXPECTED_STATE = 'unexpected_state';
	private const RAW_RESPONSE_CODE_CAPTURE_FULLY_REFUNDED = 'CAPTURE_FULLY_REFUNDED';
	private const RAW_STATUS_DECLINED = 'declined';

	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'RefundAction' );
		$refundDetails = $this->getRefundDetails( $msg );

		if ( $refundDetails->isSuccessful() ) {
			// Gravy sends a 'processing' notification following a refund request. Once complete
			// at the backend processor, they send a subsequent 'succeeded' notification which is our
			// signal to update the record in CiviCRM.
			if ( $refundDetails->getStatus() === FinalStatus::COMPLETE ) {
				$ipnMessageDate = strtotime( $msg->getMessageDate() );
				// turn the refund info in a valid refund queue consumer message
				$refundQueueMessage = $this->buildRefundQueueMessage( $ipnMessageDate, $refundDetails->getNormalizedResponse() );
				QueueWrapper::push( 'refund', $refundQueueMessage );
			} else {
				$tl->info( "Skipping in-progress refund notification for refund {$refundDetails->getGatewayRefundId()}" );
			}
		} else {
			// Get more specific error information from the refund details
			$rawResponse = $refundDetails->getRawResponse();
			$errorCode = $rawResponse['error_code'] ?? null;
			$rawResponseCode = $rawResponse['raw_response_code'] ?? null;
			$refundId = $refundDetails->getGatewayRefundId() ?? $rawResponse['id'] ?? '';

			if ( $this->isAlreadyFullyRefundedError( $errorCode, $rawResponseCode ) ) {
				$tl->info( "Refund {$refundId} failed - transaction already fully refunded" );
			} elseif ( ( $rawResponse['status'] ?? null ) === self::RAW_STATUS_DECLINED ) {
				$tl->info( "Refund {$refundId} was declined - notifying admins" );
				$this->sendDeclinedRefundAlert( $refundDetails );
			} else {
				$tl->info( "Problem locating refund with refund id {$refundId}. Error: {$errorCode}" );
			}
		}

		return true;
	}

	public function getRefundDetails( RefundMessage $msg ): RefundPaymentResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$refundDetails = $provider->getRefundDetails( [
			'gateway_refund_id' => $msg->getGatewayRefundId()
		] );

		return $refundDetails;
	}

	/**
	 * Check if the error indicates the transaction is already fully refunded
	 * So far we've seen both 'unexpected_state' and 'refund_already_satisfied' for this scenario.
	 */
	private function isAlreadyFullyRefundedError( ?string $errorCode, ?string $rawResponseCode ): bool {
		return ( $errorCode === self::ERROR_CODE_UNEXPECTED_STATE || $errorCode === self::ERROR_CODE_REFUND_ALREADY_SATISFIED )
		&& $rawResponseCode === self::RAW_RESPONSE_CODE_CAPTURE_FULLY_REFUNDED;
	}

	/**
	 * Notify admins by email that a donation's refund was declined by Gravy
	 * and so will need to be handled manually.
	 */
	private function sendDeclinedRefundAlert( RefundPaymentResponse $refundDetails ): void {
		$rawResponse = $refundDetails->getRawResponse();
		$normalizedResponse = $refundDetails->getNormalizedResponse();
		$refundId = $refundDetails->getGatewayRefundId() ?? $rawResponse['id'] ?? '';

		$config = Context::get()->getProviderConfiguration();
		$to = $config->val( 'notifications/declined-refund-alerts/to' );
		$from = $config->val( 'email/from-address' );
		$subject = 'ALERT: Gravy refund declined';
		$body = "A refund for a donation was declined by Gravy." .
			PHP_EOL . PHP_EOL .
			"Refund ID: {$refundId}" . PHP_EOL .
			"Parent transaction ID: " . ( $rawResponse['transaction_id'] ?? 'unknown' ) . PHP_EOL .
			"Payment Service Refund ID: " . ( $normalizedResponse['payment_service_refund_id'] ?? 'unknown' ) . PHP_EOL .
			"Refund Currency: " . ( $rawResponse['currency'] ?? 'unknown' ) . PHP_EOL .
			"Refund Amount: " . ( $rawResponse['amount'] ?? 'unknown' ) . PHP_EOL .
			"Reason: " . ( $rawResponse['raw_response_description'] ?? $rawResponse['reason'] ?? 'unknown' );

		try {
			MailHandler::sendEmail( $to, $subject, $body, $from );
		} catch ( \Throwable $e ) {
			$tl = new TaggedLogger( 'RefundAction' );
			$tl->error( "Failed to send declined refund alert email for refund {$refundId}: " . $e->getMessage() );
		}
	}
}
