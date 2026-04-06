<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

class CashPaymentProviderRequestMapper extends RedirectPaymentProviderRequestMapper {
	/**
	 * @param array $params
	 * @param array $request
	 * @return array
	 */
	public function addRecurringParams( array $params, array $request ): array {
		$request = parent::addRecurringParams( $params, $request );

		// additional connection_options need for pix: https://docs.gr4vy.com/connections/payments/dlocal-pix
		if ( $params['payment_submethod'] == 'pix' ) {
			$frequencyUnit = $this->frequencyUnitMapper(
				isset( $params['frequency_unit'] ) ? $params['frequency_unit'] : null,
				isset( $params['frequency_interval'] ) ? $params['frequency_interval'] : null );
			if ( !$this->isRecurringCharge( $params ) ) {
				if ( !empty( $params['recurring'] ) ) {
					$request['connection_options'] = [
						'dlocal-pix' => [
							'subscription' => [
								'frequency' => $frequencyUnit,
								'amount' => [
									'type' => 'FIXED',
									'value' => (string)$params['amount'],
									'min_value' => null, // when FIXED, null provide (if not provide min_value error return)
								],
								'start_date' => date( 'Y-m-d' ),
								'end_date' => null // null mean no end date (i.e. continue until cancelled)
							]
						]
					];
				}
			}
		}

		return $request;
	}

	private function frequencyUnitMapper( ?string $frequencyUnit, ?int $frequencyInterval = 1 ): string {
		if ( $frequencyUnit === null ) {
			return 'MONTHLY';
		}

		$frequencyUnit = strtolower( $frequencyUnit );
		$frequencyInterval = $frequencyInterval ?? 1;

		switch ( $frequencyUnit ) {
			case 'month':
				switch ( $frequencyInterval ) {
					case 1:
						return 'MONTHLY';
					case 3:
						return 'QUARTERLY';
					case 6:
					default:
						throw new \UnexpectedValueException(
							"Unsupported month interval: $frequencyInterval"
						);
				}

			case 'year':
				return 'ANNUAL';

			default:
				throw new \UnexpectedValueException(
					"Unknown frequency unit $frequencyUnit"
				);
		}
	}
}
