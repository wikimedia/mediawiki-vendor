<?php
namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\PaymentMethodResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyPaymentMethodDefinitionResponseFactory extends GravyPaymentResponseFactory {
	/**
	 * Create a basic response object for Gravy payment methods.
	 *
	 * @return PaymentMethodResponse
	 */
	protected static function createBasicResponse(): PaymentMethodResponse {
		return new PaymentMethodResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentMethodDefinitionResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentMethodDefinitionResponse, array $normalizedResponse ): void {
		if ( !$paymentMethodDefinitionResponse instanceof PaymentMethodResponse ) {
			return;
		}

		self::setSupportedCountries( $paymentMethodDefinitionResponse, $normalizedResponse );
		self::setSupportedCurrencies( $paymentMethodDefinitionResponse, $normalizedResponse );
		self::setRequiredFields( $paymentMethodDefinitionResponse, $normalizedResponse );
	}

	/**
	 * @param PaymentMethodResponse $paymentMethodDefinitionResponse
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function setSupportedCurrencies( PaymentMethodResponse $paymentMethodDefinitionResponse, array $normalizedResponse ): void {
		if ( !empty( $normalizedResponse['supported_currencies'] ) ) {
			$paymentMethodDefinitionResponse->setSupportedCurrencies( $normalizedResponse['supported_currencies'] );
		}
	}

	/**
	 * @param PaymentMethodResponse $paymentMethodDefinitionResponse
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function setSupportedCountries( PaymentMethodResponse $paymentMethodDefinitionResponse, array $normalizedResponse ): void {
		if ( !empty( $normalizedResponse['supported_countries'] ) ) {
			$paymentMethodDefinitionResponse->setSupportedCountries( $normalizedResponse['supported_countries'] );
		}
	}

	/**
	 * @param PaymentMethodResponse $paymentMethodDefinitionResponse
	 * @param array $normalizedResponse
	 * @return void
	 */
	protected static function setRequiredFields( PaymentMethodResponse $paymentMethodDefinitionResponse, array $normalizedResponse ): void {
		if ( !empty( $normalizedResponse['required_fields'] ) ) {
			$paymentMethodDefinitionResponse->setRequiredFields( $normalizedResponse['required_fields'] );
		}
	}
}
