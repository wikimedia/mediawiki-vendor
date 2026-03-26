<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Cache\CacheHelper;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Factories\GravyPaymentMethodDefinitionResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Responses\PaymentMethodResponse;

trait GetPaymentServiceDefinitionTrait {
	abstract protected function getCacheParameters(): array;

	abstract protected function getRequestMapper(): RequestMapper;

	abstract protected function getResponseMapper(): ResponseMapper;

	abstract protected function getApi(): Api;

	abstract protected function getPaymentMethod(): string;

	/**
	 * Gets the definition of a payment method on Gravy
	 * Currently, only ideal for payment methods with a unique payment service definition
	 * For example - PayPal, Venmo, and Trustly
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @return PaymentMethodResponse
	 */
	public function getPaymentServiceDefinition(): PaymentMethodResponse {
		$paymentServiceDefinitionResponse = new PaymentMethodResponse();
		try {
			$gravyPaymentServiceDefinitionRequest = $this->getRequestMapper()->mapToPaymentServiceDefinitionRequest( $this->getPaymentMethod() );
			$key = $this->makeCacheKey( $gravyPaymentServiceDefinitionRequest['method'] );

			$callback = function () use( $gravyPaymentServiceDefinitionRequest ){
				// dispatch api call to external API
				$rawGravyPaymentServiceDefinitionResponse = $this->getApi()->getPaymentServiceDefinition( $gravyPaymentServiceDefinitionRequest['method'] );
				return $rawGravyPaymentServiceDefinitionResponse;
			};

			$rawGravyPaymentServiceDefinitionResponse = CacheHelper::getWithSetCallback( $key, $this->getCacheParameters()['duration'], $callback );
			$normalizedResponse = $this->getResponseMapper()->mapFromPaymentServiceDefinitionResponse( $rawGravyPaymentServiceDefinitionResponse );
			// map the response from the external format back to our normalized structure.
			$paymentServiceDefinitionResponse = GravyPaymentMethodDefinitionResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( \UnexpectedValueException $e ) {
			// it threw an API exception!
			Logger::error( "Processor failed to fetch payment service definition for {$this->getPaymentMethod()}. returned response:" . $e->getMessage() );
			GravyPaymentMethodDefinitionResponseFactory::handleException( $paymentServiceDefinitionResponse, $e->getMessage(), $e->getCode() );
		}
		return $paymentServiceDefinitionResponse;
	}

	protected function makeCacheKey( string $method ): string {
		$base = $this->getCacheParameters()['key-base'];
		return "{$base}_get-payment-method-definition-{$method}";
	}
}
