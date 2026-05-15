<?php

namespace SmashPig\PaymentProviders\Adyen\Mapper;

use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class ApprovePaymentResponseMapper extends ResponseMapper {

	protected function mapIDs( PaymentProviderResponse $response, array $rawResponse ): void {
		if ( !( $response instanceof PaymentProviderExtendedResponse ) ) {
			throw new \InvalidArgumentException(
				'Response should be an instance of PaymentProviderExtendedResponse'
			);
		}
		if ( isset( $rawResponse['pspReference'] ) ) {
			$response->setCaptureID( $rawResponse['pspReference'] );
		}
		$this->mapPaymentPspReference( $response, $rawResponse );
	}
}
