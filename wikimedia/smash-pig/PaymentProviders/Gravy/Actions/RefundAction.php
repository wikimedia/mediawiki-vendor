<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\RefundMessage;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class RefundAction extends GravyAction {
	 public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'TransactionAction' );
		$refundDetails = $this->getRefundDetails( $msg );

		if ( $refundDetails->isSuccessful() ) {
			$message = $refundDetails->getNormalizedResponse();
			unset( $message['raw_response'] );
			$message['date'] = strtotime( $msg->getMessageDate() );
			QueueWrapper::push( 'refund', $message );
		} else {
			$tl->info(
				"Problem locating refund with refund id {$refundDetails->getGatewayRefundId()}"
			);
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
}
