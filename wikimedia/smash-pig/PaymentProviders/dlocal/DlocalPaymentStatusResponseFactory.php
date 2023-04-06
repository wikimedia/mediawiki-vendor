<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use UnexpectedValueException;

class DlocalPaymentStatusResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( $rawResponse ): PaymentProviderResponse {
		try {
			$paymentDetailResponse = new PaymentDetailResponse();
			$paymentDetailResponse->setRawResponse( $rawResponse );

			$gatewayTxnId = $rawResponse['id'] ?? null;
			if ( $gatewayTxnId ) {
				$paymentDetailResponse->setGatewayTxnId( $gatewayTxnId );
			}

			self::setStatusDetails( $paymentDetailResponse, new PaymentStatusNormalizer() );

			if ( self::isFailedTransaction( $paymentDetailResponse->getStatus() ) ) {
				self::addPaymentFailureError( $paymentDetailResponse, $rawResponse[ 'status_detail' ], $rawResponse[ 'status_code' ] );
			}
		} catch ( UnexpectedValueException $unexpectedValueException ) {
			Logger::debug( 'Payment status retrieval failed', $rawResponse );

			self::addPaymentFailureError( $paymentDetailResponse );
			$paymentDetailResponse->setStatus( FinalStatus::UNKNOWN );
			$paymentDetailResponse->setSuccessful( false );
		}

		return $paymentDetailResponse;
	}
}
