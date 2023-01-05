<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use Exception;
use PayWithAmazon\PaymentsClientInterface;
use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\AuthorizationDeclined;
use SmashPig\PaymentProviders\Amazon\ReasonCode;

class RetryAuthorization implements IListenerMessageAction {

	public function execute( ListenerMessage $msg ): bool {
		// only retry declined authorizations
		if ( !( $msg instanceof AuthorizationDeclined ) ) {
			return true;
		}
		// and only when the reason is TransactionTimedOut
		if ( !( $msg->getReasonCode() === ReasonCode::TRANSACTION_TIMED_OUT ) ) {
			return true;
		}
		$config = Context::get()->getProviderConfiguration();

		/**
		 * @var PaymentsClientInterface $client
		 */
		$client = $config->object( 'payments-client', true );

		$orderReferenceId = $msg->getOrderReferenceId();

		Logger::info(
			"Retrying timed-out authorization on order reference $orderReferenceId"
		);

		try {
			$response = $client->authorize(
				[
					'amazon_order_reference_id' => $orderReferenceId,
					'authorization_amount' => $msg->getGross(),
					'currency_code' => $msg->getCurrency(),
					'capture_now' => true, // combine authorize and capture steps
					'authorization_reference_id' => $msg->getOrderId(),
					'transaction_timeout' => 1440, // whole day to retry
				]
			)->toArray();

			if ( !empty( $response['Error'] ) ) {
				Logger::warning(
					"Error retrying auth on order reference $orderReferenceId: " .
					$response['Error']['Code'] . ': ' .
					$response['Error']['Message']
				);
				return false;
			}
		} catch ( Exception $ex ) {
			Logger::warning(
				"Error retrying auth on order reference $orderReferenceId: " .
				$ex->getMessage()
			);
			return false;
		}

		return true;
	}
}
