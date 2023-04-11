<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class DlocalCreatePaymentResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new CreatePaymentResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $rawResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $rawResponse ): void {
		if ( !$paymentResponse instanceof CreatePaymentResponse ) {
			return;
		}
		self::setRedirectURL( $rawResponse, $paymentResponse );
		self::setRecurringPaymentToken( $paymentResponse, $rawResponse );
	}

	protected static function getStatusNormalizer(): PaymentStatusNormalizer {
		return new PaymentStatusNormalizer();
	}

	/**
	 * @param array $rawResponse
	 * @return bool
	 */
	protected static function responseHasCardRecurringPaymentToken( array $rawResponse ): bool {
		return array_key_exists( 'card', $rawResponse ) && array_key_exists( 'card_id', $rawResponse['card'] );
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $rawResponse
	 * @return void
	 */
	protected static function setRecurringPaymentToken( PaymentProviderResponse $paymentResponse, array $rawResponse ): void {
		if ( !$paymentResponse instanceof CreatePaymentResponse ) {
			return;
		}
		if ( self::responseHasCardRecurringPaymentToken( $rawResponse ) ) {
			$token = self::getCardRecurringPaymentTokenFromRawResponse( $rawResponse );
			$paymentResponse->setRecurringPaymentToken( $token );
		}
	}

	/**
	 * @param array $rawResponse
	 * @return string
	 */
	protected static function getCardRecurringPaymentTokenFromRawResponse( array $rawResponse ): string {
		return $rawResponse['card']['card_id'];
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
