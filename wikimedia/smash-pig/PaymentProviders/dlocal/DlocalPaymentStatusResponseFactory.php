<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\Responses\PaymentStatusResponseFactory;

class DlocalPaymentStatusResponseFactory extends PaymentStatusResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return PaymentDetailResponse
	 */
	public static function fromRawResponse( $rawResponse ): PaymentDetailResponse {
		$paymentDetailResponse = new PaymentDetailResponse();
		$paymentDetailResponse->setRawResponse( $rawResponse );

		$gatewayTxnId = $rawResponse['id'] ?? null;
		if ( $gatewayTxnId ) {
			$paymentDetailResponse->setGatewayTxnId( $gatewayTxnId );
		}

		$rawStatus = $rawResponse['status'] ?? null;
		if ( $rawStatus ) {
			self::setStatusDetails( $paymentDetailResponse, $rawStatus );
		} else {
			$paymentDetailResponse->setStatus( FinalStatus::UNKNOWN );
			$paymentDetailResponse->setSuccessful( false );
		}

		return $paymentDetailResponse;
	}

	/**
	 * @param PaymentDetailResponse $paymentDetailResponse
	 * @param string $rawStatus
	 * @return void
	 */
	private static function setStatusDetails( PaymentDetailResponse $paymentDetailResponse, string $rawStatus ): void {
		$paymentDetailResponse->setRawStatus( $rawStatus );
		$paymentStatusNormalizer = new PaymentStatusNormalizer();
		$normalizedStatus = $paymentStatusNormalizer->normalizeStatus( $rawStatus );
		$paymentDetailResponse->setStatus( $normalizedStatus );
		$isSuccessfulStatus = $paymentStatusNormalizer->isSuccessStatus( $rawStatus );
		$paymentDetailResponse->setSuccessful( $isSuccessfulStatus );
	}

}
