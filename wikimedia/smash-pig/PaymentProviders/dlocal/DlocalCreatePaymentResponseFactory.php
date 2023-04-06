<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use UnexpectedValueException;

class DlocalCreatePaymentResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {
	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( $rawResponse ): PaymentProviderResponse {
		try {
			$createPaymentResponse = new CreatePaymentResponse();
			$createPaymentResponse->setRawResponse( $rawResponse );
			$gatewayTxnId = $rawResponse['id'] ?? null;
			if ( $gatewayTxnId ) {
				$createPaymentResponse->setGatewayTxnId( $gatewayTxnId );
			}
			self::setRedirectURL( $rawResponse, $createPaymentResponse );
			if ( self::responseHasRecurringPaymentToken( $rawResponse ) ) {
				$createPaymentResponse->setRecurringPaymentToken( $rawResponse['card']['card_id'] );
			}

			self::setStatusDetails( $createPaymentResponse, new PaymentStatusNormalizer() );

			if ( self::isFailedTransaction( $createPaymentResponse->getStatus() ) ) {
				self::addPaymentFailureError( $createPaymentResponse, $rawResponse[ 'status_detail' ], $rawResponse[ 'status_code' ] );
			}
		} catch ( UnexpectedValueException $unexpectedValueException ) {
			Logger::debug( 'Create Payment failed', $rawResponse );

			self::addPaymentFailureError( $createPaymentResponse );
			$createPaymentResponse->setStatus( FinalStatus::UNKNOWN );
			$createPaymentResponse->setSuccessful( false );
		}
		return $createPaymentResponse;
	}

	/**
	 * @param array $rawResponse
	 * @return bool
	 */
	protected static function responseHasRecurringPaymentToken( array $rawResponse ): bool {
		return array_key_exists( 'card', $rawResponse ) && array_key_exists( 'card_id', $rawResponse['card'] );
	}

	/**
	 * @param array $rawResponse
	 * @param CreatePaymentResponse $createPaymentResponse
	 * @return void
	 */
	protected static function setRedirectURL( array $rawResponse, CreatePaymentResponse $createPaymentResponse ): void {
		if ( array_key_exists( 'redirect_url', $rawResponse ) ) {
			$createPaymentResponse->setRedirectUrl( $rawResponse['redirect_url'] );
		}

		if ( array_key_exists( 'three_dsecure', $rawResponse )
			&& array_key_exists( 'redirect_url', $rawResponse['three_dsecure'] ) ) {
			$createPaymentResponse->setRedirectUrl( $rawResponse['three_dsecure']['redirect_url'] );
		}
	}
}
