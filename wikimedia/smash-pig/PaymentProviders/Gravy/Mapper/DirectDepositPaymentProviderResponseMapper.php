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
		if ( !empty( $response['buyer'] ) && !empty( $response['buyer']['billing_details'] ) ) {
			$donorBillingDetails = $response['buyer']['billing_details'];
			$externalIdentifier = $response['buyer']['external_identifier'] ?? '';
			$result['donor_details']['email_address'] = $externalIdentifier;
			if ( isset( $donorBillingDetails['email_address'] ) ) {
				// compare if the email address from the form is different from the one in trustly in lowercase
				$externalEmail = strtolower( $externalIdentifier );
				$billingEmail = strtolower( $donorBillingDetails['email_address'] );
				if ( $externalEmail !== $billingEmail ) {
					$result['donor_details']['billing_email'] = $donorBillingDetails['email_address'];
				}
			}
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
