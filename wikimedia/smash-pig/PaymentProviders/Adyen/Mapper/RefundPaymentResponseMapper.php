<?php

namespace SmashPig\PaymentProviders\Adyen\Mapper;

use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class RefundPaymentResponseMapper extends ResponseMapper {

	protected function mapIDs( PaymentProviderResponse $response, array $rawResponse ): void {
		if ( !( $response instanceof RefundPaymentResponse ) ) {
			throw new \InvalidArgumentException(
				'Response should be an instance of PaymentProviderExtendedResponse'
			);
		}
		if ( isset( $rawResponse['pspReference'] ) ) {
			$response->setGatewayRefundId( $rawResponse['pspReference'] );
		}
		$this->mapPaymentPspReference( $response, $rawResponse['paymentPspReference'] ?? null );
	}
}
