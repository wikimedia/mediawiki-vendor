<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentProviders\Amazon\AmazonApi;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\PaymentCapture;

/**
 * Looks up our reference ID for transactions pushed through manually
 */
class ReconstructMerchantReference implements IListenerMessageAction {

	public function execute( ListenerMessage $msg ): bool {
		// Bail out if not a PaymentCapture
		if ( !( $msg instanceof PaymentCapture ) ) {
			return true;
		}
		$captureReference = $msg->getOrderId();
		if ( !AmazonApi::isAmazonGeneratedMerchantReference( $captureReference ) ) {
			// We only have to fix Amazon-generated IDs with that prefix
			return true;
		}

		$orderReferenceId = $msg->getOrderReferenceId();
		Logger::info(
			"Looking up merchant reference for OrderReference $orderReferenceId"
		);
		try {
			$orderId = AmazonApi::get()->findMerchantReference( $orderReferenceId );
			if ( $orderId ) {
				$msg->setOrderId( $orderId );
			}
			return true;
		} catch ( SmashPigException $ex ) {
			Logger::error( $ex->getMessage() );
			return false;
		}
	}
}
