<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use Exception;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;

class CloseOrderReference implements IListenerMessageAction {

	public function execute( ListenerMessage $msg ): bool {
		// only close after successful capture
		if ( !( $msg instanceof CaptureCompleted ) ) {
			return true;
		}

		$config = Context::get()->getProviderConfiguration();
		$client = $config->object( 'payments-client', true );

		$orderReferenceId = $msg->getOrderReferenceId();

		Logger::info( "Closing order reference $orderReferenceId" );

		// Failure is unexpected, but shouldn't stop us recording
		// the successful capture
		try {
			$response = $client->closeOrderReference(
				[
					'amazon_order_reference_id' => $orderReferenceId,
				]
			)->toArray();

			if ( !empty( $response['Error'] ) ) {
				Logger::warning(
					"Error closing order reference $orderReferenceId: " .
					$response['Error']['Code'] . ': ' .
					$response['Error']['Message']
				);
				return true;
			}
		} catch ( Exception $ex ) {
			Logger::warning(
				"Error closing order reference $orderReferenceId: " .
				$ex->getMessage()
			);
			return true;
		}

		return true;
	}
}
