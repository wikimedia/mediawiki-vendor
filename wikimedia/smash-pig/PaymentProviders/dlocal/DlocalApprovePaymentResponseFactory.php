<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponseFactory;

class DlocalApprovePaymentResponseFactory extends ApprovePaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return ApprovePaymentResponse
	 */
	public static function fromRawResponse( $rawResponse ): ApprovePaymentResponse {
		$approvePaymentResponse = new ApprovePaymentResponse();
		$approvePaymentResponse->setRawResponse( $rawResponse );
		$gatewayTxnId = $rawResponse['id'] ?? null;
		if ( $gatewayTxnId ) {
			$approvePaymentResponse->setGatewayTxnId( $gatewayTxnId );
		}
		$rawStatus = $rawResponse['status'] ?? null;
		if ( $rawStatus ) {
			self::setStatusDetails( $approvePaymentResponse, $rawStatus );
		} else {
			$approvePaymentResponse->setStatus( FinalStatus::UNKNOWN );
			$approvePaymentResponse->setSuccessful( false );
		}
		return $approvePaymentResponse;
	}

	/**
	 * @param ApprovePaymentResponse $approvePaymentResponse
	 * @param string|null $rawStatus
	 * @return void
	 */
	protected static function setStatusDetails( ApprovePaymentResponse $approvePaymentResponse, ?string $rawStatus ): void {
		$approvePaymentResponse->setRawStatus( $rawStatus );
		$approvePaymentStatusNormalizer = new ApprovePaymentStatusNormalizer();
		$normalizedStatus = $approvePaymentStatusNormalizer->normalizeStatus( $rawStatus );
		$approvePaymentResponse->setStatus( $normalizedStatus );
		$isSuccessfulStatus = $approvePaymentStatusNormalizer->isSuccessStatus( $rawStatus );
		$approvePaymentResponse->setSuccessful( $isSuccessfulStatus );
	}
}
