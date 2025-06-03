<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentData\FinalStatus;

class DirectDepositPaymentProviderResponseMapper extends ResponseMapper {
	/**
	 * ACH user email from form as external identifier in console and store billing email from trustly
	 * @param array &$result
	 * @param array $response
	 * @return void
	 */
	protected function mapPaymentResponseDonorDetails( array &$result, array $response ): void {
		parent::mapPaymentResponseDonorDetails( $result, $response );
		$donorDetails = $response['buyer']['billing_details'];
		$result['donor_details']['email_address'] = $response['buyer']['external_identifier'];
		if ( isset( $donorDetails['email_address'] ) ) {
			$result['donor_details']['billing_email'] = $donorDetails['email_address'];
		}
	}

	/**
	 * @param string $paymentProcessorStatus
	 * @return string
	 */
	protected function normalizeStatus( string $paymentProcessorStatus ): string {
		if ( $paymentProcessorStatus === 'processing' || $paymentProcessorStatus === 'capture_pending' ) {
			return FinalStatus::COMPLETE;
		}
		return parent::normalizeStatus( $paymentProcessorStatus );
	}

}
