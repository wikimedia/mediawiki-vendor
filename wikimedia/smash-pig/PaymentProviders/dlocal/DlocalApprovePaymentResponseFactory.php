<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use UnexpectedValueException;

class DlocalApprovePaymentResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( $rawResponse ): PaymentProviderResponse {
		try {
			$approvePaymentResponse = new ApprovePaymentResponse();
			$approvePaymentResponse->setRawResponse( $rawResponse );
			$gatewayTxnId = $rawResponse['id'] ?? null;
			if ( $gatewayTxnId ) {
				$approvePaymentResponse->setGatewayTxnId( $gatewayTxnId );
			}

			self::setStatusDetails( $approvePaymentResponse, new ApprovePaymentStatusNormalizer() );

			if ( self::isFailedTransaction( $approvePaymentResponse->getStatus() ) ) {
				self::addPaymentFailureError( $approvePaymentResponse, $rawResponse[ 'status_detail' ], $rawResponse[ 'status_code' ] );
			}
		} catch ( UnexpectedValueException $unexpectedValueException ) {
			Logger::debug( 'Approve Payment failed', $rawResponse );

			self::addPaymentFailureError( $approvePaymentResponse );
			$approvePaymentResponse->setStatus( FinalStatus::UNKNOWN );
			$approvePaymentResponse->setSuccessful( false );
		}
		return $approvePaymentResponse;
	}
}
