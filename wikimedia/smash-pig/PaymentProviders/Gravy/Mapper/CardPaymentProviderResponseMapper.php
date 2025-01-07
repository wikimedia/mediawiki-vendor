<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentData\FinalStatus;

class CardPaymentProviderResponseMapper extends ResponseMapper {
	/**
	 * @return array
	 * @link https://docs.gr4vy.com/reference/checkout-sessions/new-checkout-session
	 */
	public function mapFromCreatePaymentSessionResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}
		$params = [
			'is_successful' => true,
			'gateway_session_id' => $response['id'],
			'raw_status' => '',
			'status' => FinalStatus::PENDING,
			'raw_response' => $response
		];

		return $params;
	}
}
