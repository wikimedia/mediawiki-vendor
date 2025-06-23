<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetLatestPaymentStatusResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class TransactionDetailsNormalizer {
	protected ProviderConfiguration $providerConfiguration;

	public function __construct() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
	}

	/**
	 * Normalizes transaction details to PaymentProviderResponse.
	 *
	 * @param string $paymentMethod The payment method used for this transaction.
	 * @param array $transactionResponse The raw response received from gravy.
	 * @return PaymentProviderResponse A normalized representation of the transaction details.
	 */
	public function normalizeTransactionDetails(
		string $paymentMethod,
		array $transactionResponse
	): PaymentProviderResponse {
		$responseMapper = $this->getResponseMapperForPaymentMethod( $paymentMethod );
		$normalizedResponse = $responseMapper->mapFromPaymentResponse( $transactionResponse );

		return GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse( $normalizedResponse );
	}

	/**
	 * Retrieves the response mapper specific to a given payment method.
	 *
	 * @param string $paymentMethod The payment method for which the response mapper is required.
	 * @return ResponseMapper The configured response mapper for the specified payment method.
	 * @throws \InvalidArgumentException If no response mapper is configured for the given payment method.
	 */
	private function getResponseMapperForPaymentMethod( string $paymentMethod
	): ResponseMapper {
		$shorthandPaymentMethod = ReferenceData::getShorthandPaymentMethod( $paymentMethod );
		$mapperConfigKey = "mappers/{$shorthandPaymentMethod}-response";

		if ( !$this->providerConfiguration->nodeExists( $mapperConfigKey ) ) {
			throw new \InvalidArgumentException( "No response mapper configured for payment method: {$shorthandPaymentMethod} (config key: {$mapperConfigKey})" );
		}

		return $this->providerConfiguration->object( $mapperConfigKey );
	}

}
