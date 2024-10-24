<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\GravyHelper;
use SmashPig\PaymentProviders\Gravy\ReferenceData;
use SmashPig\PaymentProviders\RiskScorer;

class ResponseMapper {
	// List of methods with username as identifiers
	const METHODS_WITH_USERNAME = [ 'venmo' ];

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
		if ( $this->paymentResponseContainsError( $response ) ) {
			return $this->mapErrorFromResponse( $response );
		}

		$result = [
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

		if ( $result['status'] == FinalStatus::FAILED ) {
			$result['is_successful'] = false;
		}

		if ( !empty( $response['payment_method'] ) ) {
			$result['recurring_payment_token'] = $response['payment_method']['id'];

			$gravyPaymentMethod = $response['payment_method']['method'] ?? '';
			$gravyPaymentSubmethod = $response['payment_method']['scheme'] ?? '';
			[ $normalizedPaymentMethod, $normalizedPaymentSubmethod ] = ReferenceData::decodePaymentMethod( $gravyPaymentMethod, $gravyPaymentSubmethod );
			$result['payment_method'] = $normalizedPaymentMethod;
			$result['payment_submethod'] = $normalizedPaymentSubmethod;

			if ( !empty( $response['payment_method']['approval_url'] ) ) {
				$result['redirect_url'] = $response['payment_method']['approval_url'];
			}
		}

		if ( !empty( $response['buyer'] ) && !empty( $response['buyer']['billing_details'] ) ) {
			$donorDetails = $response['buyer']['billing_details'];
			$result['donor_details'] = [
				'first_name' => $donorDetails['first_name'] ?? '',
				'last_name' => $donorDetails['last_name'] ?? '',
				'phone_number' => $donorDetails['phone_number'] ?? '',
				'email_address' => $donorDetails['email_address'] ?? '',
				'employer' => $response['buyer']['organization'] ?? '',
				'processor_contact_id' => $response['buyer']['id'] ?? '',
				];

			if ( in_array( $gravyPaymentMethod, self::METHODS_WITH_USERNAME ) ) {
				$result['donor_details']['username'] = $response['payment_method']['label'];
			}

			if ( !empty( $donorDetails['address'] ) ) {
				$donorAddress = $donorDetails['address'];
				$result['donor_details']['address'] = [
					'address_line1' => $donorAddress['line1'] ?? '',
					'postal_code' => $donorAddress['postal_code'] ?? '',
					'state' => $donorAddress['state'] ?? '',
					'city' => $donorAddress['city'] ?? '',
					'country' => $donorAddress['country'] ?? '',
				];
			}
		}

		if ( !empty( $response['payment_service'] ) ) {
			if ( !empty( $response['payment_service']['payment_service_definition_id'] ) ) {
				$paymentServiceDefinitionId = $response['payment_service']['payment_service_definition_id'];
				$result['backend_processor'] = GravyHelper::extractProcessorNameFromServiceDefinitionId( $paymentServiceDefinitionId );
			}
		}
		$result['backend_processor_transaction_id'] = $response['payment_service_transaction_id'] ?? null;

		return $result;
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
	 * @param array $response
	 * @return array
	 */
	public function mapFromRefundPaymentResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}
		return [
			"is_successful" => true,
			"gateway_parent_id" => $response["transaction_id"],
			"gateway_refund_id" => $response["id"],
			"currency" => $response["currency"],
			"amount" => $response["amount"] / 100,
			"reason" => $response["reason"],
			"status" => $this->normalizeStatus( $response["status"] ),
			"raw_status" => $response["status"],
			"type" => 'refund',
			"raw_response" => $response,
		];
	}

	/**
	 * @param array $response
	 * @return array
	 */
	public function mapFromReportExecutionResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}
		$report = $response["report"];
		return [
			"is_successful" => true,
			"report_execution_id" => $response["id"],
			"report_id" => $report["id"],
			"raw_response" => $response,
			"status" => $this->normalizeStatus( $response["status"] ),
			"raw_status" => $response["status"]
		];
	}

	/**
	 * @param array $response
	 * @return array
	 */
	public function mapFromGenerateReportUrlResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}

		return [
			"is_successful" => true,
			"report_url" => $response["url"],
			"expires" => $response["expires_at"],
			"raw_response" => $response,
			"status" => $this->normalizeStatus( "succeeded" ),
			"raw_status" => "succeeded"
		];
	}

	/**
	 * @param string $paymentProcessorStatus
	 * @return string
	 * @link https://docs.gr4vy.com/guides/api/resources/transactions/statuses
	 */
	protected function normalizeStatus( string $paymentProcessorStatus ): string {
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
			case 'succeeded':
				$normalizedStatus = FinalStatus::COMPLETE;
				break;
			default:
				throw new \UnexpectedValueException( "Unknown status $paymentProcessorStatus" );
		}

		return $normalizedStatus;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	private function mapErrorFromResponse( array $error ): array {
		$errorParameters = [
			"code" => '',
			"message" => '',
			"description" => ''
		];
		if ( $error['type'] == 'error' ) {
			$errorParameters['code'] = $error['status'] ?? '';
			$errorParameters['message'] = $error['code'] ?? '';
			$errorParameters['description'] = $error['message'] ?? '';
		} elseif ( $error['intent_outcome'] == 'failed' ) {
			$errorParameters['code'] = $error['error_code'] ?? '';
			$errorParameters['message'] = $error['status'] ?? '';
		} else {
			$errorParameters['code'] = $error['error_code'] ?? '';
			$errorParameters['message'] = $error['raw_response_code'] ?? '';
			$errorParameters['description'] = $error['raw_response_description'] ?? '';
		}

		if ( ( isset( $error['three_d_secure'] ) && $error['three_d_secure']['status'] === 'error' ) ) {
			$errorParameters = $this->mapFrom3DSecureErrorResponse( $error['three_d_secure'] );
		}

		$error_code = ErrorMapper::getError( $errorParameters['code'] );

		return [
			'is_successful' => false,
			'status' => FinalStatus::FAILED,
			'code' => $error_code,
			'message' => $errorParameters['message'],
			'description' => $errorParameters['description'],
			'raw_response' => $error

		];
	}

	protected function mapFrom3DSecureErrorResponse( array $params ) {
		$error_data = $params['error_data'];
		$error = [
			"code" => $error_data['code'],
			"message" => $error_data['description'],
			"description" => $error_data['detail']
		];

		return $error;
	}

	/**
	 * @param array $response
	 * @return bool
	 */
	protected function paymentResponseContainsError( array $response ): bool {
		return (
			// response type = error
			isset( $response['type'] ) && $response['type'] === 'error' )
			// contains error code
			|| isset( $response['error_code'] )
			// failure
			|| $response['intent_outcome'] === 'failed'
			// 3d secure errors
			|| ( isset( $response['three_d_secure'] ) && $response['three_d_secure']['status'] === 'error' );
	}
}
