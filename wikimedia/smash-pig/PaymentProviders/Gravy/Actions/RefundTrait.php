<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

trait RefundTrait {
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
		unset( $refundDetails['is_successful'] );

		return $refundDetails;
	}
}
