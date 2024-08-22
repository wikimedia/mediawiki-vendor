<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\ReferenceData;
use SmashPig\PaymentProviders\RiskScorer;

class ResponseMapper {
	/**
	 * @return array
	 */

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

	/**
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/new-transaction
	 */
	public function mapFromPaymentResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}

		$params = [
			'is_successful' => true,
			'gateway_txn_id' => $response['id'],
			'amount' => $response['amount'] / 100,
			'currency' => $response['currency'],
			'order_id' => $response['external_identifier'],
			'raw_status' => $response['status'],
			'status' => $this->normalizeStatus( $response['status'] ),
			'raw_response' => $response,
			'risk_scores' => $this->getRiskScores( $response['avs_response_code'] ?? null, $response['cvv_response_code'] ?? null )
		];

		if ( $params['status'] == FinalStatus::FAILED ) {
			$params['is_successful'] = false;
		}

		if ( !empty( $response['payment_method'] ) ) {
			$params['recurring_payment_token'] = $response['payment_method']['id'];

			$paymentMethod = $response['payment_method']['method'] ?? '';
			$paymentSubmethod = $response['payment_method']['scheme'] ?? '';
			[ $method, $submethod ] = ReferenceData::decodePaymentMethod( $paymentMethod, $paymentSubmethod );
			$params['payment_method'] = $method;
			$params['payment_submethod'] = $submethod;

			if ( !empty( $response['payment_method']['approval_url'] ) ) {
				$params['redirect_url'] = $response['payment_method']['approval_url'];
			}
		}

		if ( !empty( $response['buyer'] ) && !empty( $response['buyer']['billing_details'] ) ) {
			$donorDetails = $response['buyer']['billing_details'];
			$params['donor_details'] = [
				'first_name' => $donorDetails['first_name'] ?? '',
				'last_name' => $donorDetails['last_name'] ?? '',
				'phone_number' => $donorDetails['phone_number'] ?? '',
				'email_address' => $donorDetails['email_address'] ?? '',
				'employer' => $response['buyer']['organization'] ?? '',
				'processor_contact_id' => $response['buyer']['id'] ?? '',
				];
			if ( !empty( $donorDetails['address'] ) ) {
				$donorAddress = $donorDetails['address'];
				$params['donor_details']['address'] = [
					'address_line1' => $donorAddress['line1'] ?? '',
					'postal_code' => $donorAddress['postal_code'] ?? '',
					'state' => $donorAddress['state'] ?? '',
					'city' => $donorAddress['city'] ?? '',
					'country' => $donorAddress['country'] ?? '',
				];
			}
		}

		return $params;
	}

	public function mapDonorResponse( array $response ) : array {
		$buyer = $response;
		$donorDetails = $buyer['billing_details'] ?? [];
		$params = [
			'status' => FinalStatus::COMPLETE,
			'is_successful' => true,
			'donor_details' => [
				'processor_contact_id' => $buyer['id'] ?? '',
			],
			'raw_response' => $response
		];

		if ( !empty( $donorDetails ) ) {
			$params['donor_details'] = array_merge( $params['donor_details'], [
				'first_name' => $donorDetails['first_name'] ?? '',
				'last_name' => $donorDetails['last_name'] ?? '',
				'phone_number' => $donorDetails['phone_number'] ?? '',
				'email_address' => $donorDetails['email_address'] ?? '',
				] );
			if ( !empty( $donorDetails['address'] ) ) {
				$donorAddress = $donorDetails['address'];
				$params['donor_details']['address'] = [
					'address_line1' => $donorAddress['line1'] ?? '',
					'postal_code' => $donorAddress['postal_code'] ?? '',
					'state' => $donorAddress['state'] ?? '',
					'city' => $donorAddress['city'] ?? '',
					'country' => $donorAddress['country'] ?? '',
				];
			}
		} else {
			$params['is_successful'] = false;
		}

		return $params;
	}

	public function mapFromCreateDonorResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}
		return $this->mapDonorResponse( $response );
	}

	public function mapFromGetDonorResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}

		$donorResponse = [];
		if ( !empty( $response['items'] ) ) {
			$donorResponse = $response['items'][0];
		}

		return $this->mapDonorResponse( $donorResponse );
	}

	public function getRiskScores( ?string $avs_response, ?string $cvv_response ): array {
		return ( new RiskScorer() )->getRiskScores(
			$avs_response,
			$cvv_response
		);
	}

	/**
	 * @return array
	 */
	public function mapFromCardApprovePaymentResponse(): array {
		$request = [];
		return $request;
	}

	public function mapFromDeletePaymentTokenResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}
		return [
			"is_successful" => true
		];
	}

	/**
	 * @param string $paymentProcessorStatus
	 * @return string
	 * @link https://docs.gr4vy.com/guides/api/resources/transactions/statuses
	 */
	private function normalizeStatus( string $paymentProcessorStatus ): string {
		switch ( $paymentProcessorStatus ) {
			case 'authorization_succeeded':
				$normalizedStatus = FinalStatus::PENDING_POKE;
				break;
			case 'processing':
			case 'buyer_approval_pending':
			case 'authorization_void_pending':
			case 'capture_pending':
				$normalizedStatus = FinalStatus::PENDING;
				break;
			case 'authorization_declined':
			case 'authorization_failed':
				$normalizedStatus = FinalStatus::FAILED;
				break;
			case 'authorization_voided':
				$normalizedStatus = FinalStatus::CANCELLED;
				break;
			case 'capture_succeeded':
				$normalizedStatus = FinalStatus::COMPLETE;
				break;
			default:
				throw new UnexpectedValueException( "Unknown status $paymentProcessorStatus" );
		}

		return $normalizedStatus;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	private function mapErrorFromResponse( array $params ): array {
		 $error = $params;
		 $code = null;
		 $message = '';
		 $description = '';
		if ( $error['type'] == 'error' ) {
			$code = $error['status'];
			$message = $error['code'];
			$description = $error['message'];
		} else {
			$code = $error['error_code'];
			$message = $error['raw_response_code'];
			$description = $error['raw_response_description'];
		}

		$error_code = ErrorMapper::getError( $code );

		return [
			'is_successful' => false,
			'status' => FinalStatus::FAILED,
			'code' => $error_code,
			'message' => $message,
			'description' => $description,
			'raw_response' => $error

		];
	}

}
