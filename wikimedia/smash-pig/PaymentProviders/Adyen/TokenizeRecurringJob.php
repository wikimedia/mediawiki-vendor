<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class TokenizeRecurringJob implements Runnable {

	/**
	 * @var array
	 */
	public $payload;

	public static function fromDonationMessage( array $message ): array {
		$job = [
			'class' => '\SmashPig\PaymentProviders\Adyen\TokenizeRecurringJob',
			'payload' => $message
		];
		return $job;
	}

	public static function donationNeedsTokenizing( array $donationMessage ): bool {
		$isRecurring = isset( $donationMessage['recurring'] ) && $donationMessage['recurring'];
		$needsToken = empty( $donationMessage['recurring_payment_token'] );
		return $isRecurring && $needsToken;
	}

	/**
	 * Do whatever it is that you do.
	 */
	public function execute() {
		/**
		 * @var PaymentProvider
		 */
		$provider = PaymentProviderFactory::getProviderForMethod( 'cc' );
		$processorContactId = $this->payload['processor_contact_id'];
		$tokenResult = $provider->getSavedPaymentDetails( $processorContactId )->first();
		if ( $tokenResult ) {
			$this->payload['recurring_payment_token'] = $tokenResult->getToken();
			QueueWrapper::push( 'donations', $this->payload );
			return true;
		}
		Logger::warning( "Could not find token for shopper reference $processorContactId" );
		return false;
	}
}
