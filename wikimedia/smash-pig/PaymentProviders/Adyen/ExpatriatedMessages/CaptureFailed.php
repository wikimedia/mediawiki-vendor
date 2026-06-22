<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

class CaptureFailed extends AdyenMessage {

	/**
	 * As with Capture messages, Adyen CAPTURE_FAILED webhooks for
	 * Gravy-orchestrated payments arrive with a colon-separated
	 * merchantReference. The colon isn't a Base62 character so toUuid()
	 * would fail. Nothing reads orchestratorTransactionID for failed
	 * captures, so leave it null.
	 */
	protected function getTransactionIdFromBase62MerchantReference( string $merchantReference ): ?string {
		if ( str_contains( $merchantReference, ':' ) ) {
			return null;
		}
		return parent::getTransactionIdFromBase62MerchantReference( $merchantReference );
	}
}
