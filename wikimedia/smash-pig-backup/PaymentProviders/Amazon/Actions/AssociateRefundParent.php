<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentProviders\Amazon\AmazonApi;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundCompleted;

/**
 * Associate refunds with their parent contribution
 */
class AssociateRefundParent implements IListenerMessageAction {

	public function execute( ListenerMessage $msg ): bool {
		// Bail out if not a refund
		if ( !( $msg instanceof RefundCompleted ) ) {
			return true;
		}

		$orderReferenceId = $msg->getOrderReferenceId();
		Logger::info( "Looking up capture ID for order reference $orderReferenceId" );
		try {
			$parentId = AmazonApi::get()->findCaptureId( $orderReferenceId );
			$msg->setParentId( $parentId );
			return true;
		} catch ( SmashPigException $ex ) {
			Logger::error( $ex->getMessage() );
			return false;
		}
	}
}
