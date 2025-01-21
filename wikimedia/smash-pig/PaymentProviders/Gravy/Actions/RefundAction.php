<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\RefundMessage;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class RefundAction extends GravyAction {
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
			$tl->info( "Problem locating refund with refund id {$refundDetails->getGatewayRefundId()}" );
		}

		return true;
	}

	public function getRefundDetails( RefundMessage $msg ): RefundPaymentResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$refundDetails = $provider->getRefundDetails( [
			"gateway_refund_id" => $msg->getGatewayRefundId()
		] );

		return $refundDetails;
	}

	/**
	 * Splice and dice refund details to keep the refund queue consumer happy
	 *
	 * @param string $ipnMessageDate
	 * @param array $refundDetails
	 * @return array
	 */
	protected function buildRefundQueueMessage( string $ipnMessageDate, array $refundDetails ): array {
		// Add additional required message properties
		$refundDetails['date'] = $ipnMessageDate;
		$refundDetails['gateway'] = 'gravy';
		$refundDetails['gross_currency'] = $refundDetails['currency'];
		$refundDetails['gross'] = $refundDetails['amount'];

		// Remove raw response data as it's not used/needed.
		unset( $refundDetails['raw_response'] );

		return $refundDetails;
	}
}
