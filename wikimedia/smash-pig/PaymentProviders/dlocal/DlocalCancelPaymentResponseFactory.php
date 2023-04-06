<?php
namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use UnexpectedValueException;

class DlocalCancelPaymentResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( $rawResponse ): PaymentProviderResponse {
		try {
			$cancelPaymentResponse = new CancelPaymentResponse();
			$cancelPaymentResponse->setRawResponse( $rawResponse );
			$gatewayTxnId = $rawResponse['id'] ?? null;
			if ( $gatewayTxnId ) {
				$cancelPaymentResponse->setGatewayTxnId( $gatewayTxnId );
			}

			self::setStatusDetails( $cancelPaymentResponse, new CancelPaymentStatusNormalizer() );

			if ( self::isFailedTransaction( $cancelPaymentResponse->getStatus() ) ) {
				self::addPaymentFailureError( $cancelPaymentResponse, $rawResponse[ 'status_detail' ], $rawResponse[ 'status_code' ] );
			}
		} catch ( UnexpectedValueException $unexpectedValueException ) {
			$responseError = 'cancelResult element missing from dlocal cancel response.';
			Logger::debug( $responseError, $rawResponse );

			self::addPaymentFailureError( $cancelPaymentResponse, $responseError );
			$cancelPaymentResponse->setStatus( FinalStatus::UNKNOWN );
			$cancelPaymentResponse->setSuccessful( false );
		}
		return $cancelPaymentResponse;
	}
}
