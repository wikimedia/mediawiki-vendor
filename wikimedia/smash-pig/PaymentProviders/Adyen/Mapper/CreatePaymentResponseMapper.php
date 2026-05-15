<?php

namespace SmashPig\PaymentProviders\Adyen\Mapper;

use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class CreatePaymentResponseMapper extends ResponseMapper {

	protected function mapIDs( PaymentProviderResponse $response, array $rawResponse ): void {
		if ( isset( $rawResponse['pspReference'] ) ) {
			$response->setGatewayTxnId( $rawResponse['pspReference'] );

			if ( $response instanceof PaymentProviderExtendedResponse ) {
				$response->setAuthID( $rawResponse['pspReference'] );
				$response->setBackendProcessorTransactionId( $rawResponse['pspReference'] );
			}
		}
	}
}
