<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\GravyHelper;
use SmashPig\PaymentProviders\Gravy\PaymentMethod;
use SmashPig\PaymentProviders\Gravy\ReferenceData;
use SmashPig\PaymentProviders\RiskScorer;

class ResponseMapper {
	// List of methods with username as identifiers
	public const METHODS_WITH_USERNAME = [ 'venmo' ];

	/**
	 * @return array
	 * @link https://docs.gr4vy.com/reference/transactions/new-transaction
	 */
	public function mapFromPaymentResponse( array $response ): array {
		if ( $this->paymentResponseContainsError( $response ) ) {
			return $this->mapErrorFromResponse( $response );
		}

		return $this->mapSuccessfulPaymentResponse( $response );
	}

	public function getRiskScores( ?string $avs_response, ?string $cvv_response ): array {
		return ( new RiskScorer() )->getRiskScores(
			$avs_response,
			$cvv_response
		);
	}

	public function mapFromDeletePaymentTokenResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}
		return [
			'is_successful' => true
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
		return $this->mapSuccessfulRefundMessage( $response );
	}

	/**
	 * @param array $response
	 * @return array
	 */
	public function mapFromReportExecutionResponse( array $response ): array {
		if ( ( isset( $response['type'] ) && $response['type'] == 'error' ) || isset( $response['error_code'] ) ) {
			return $this->mapErrorFromResponse( $response );
		}
		$report = $response['report'];
		return [
			'is_successful' => true,
			'report_execution_id' => $response['id'],
			'report_id' => $report['id'],
			'report_name' => $report['name'],
			'report_created_by' => $report['creator_display_name'],
			'raw_response' => $response,
			'status' => $this->normalizeStatus( $response['status'] ),
			'raw_status' => $response['status']
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
			'is_successful' => true,
			'report_url' => $response['url'],
			'expires' => $response['expires_at'],
			'raw_response' => $response,
			'status' => $this->normalizeStatus( 'succeeded' ),
			'raw_status' => 'succeeded'
		];
	}

	/**
	 * Maps from gravy payment response payment method details
	 * @param array &$result
	 * @param array $response
	 * @return void
	 */
	private function mapPaymentResponsePaymentMethodDetails( array &$result, array $response ): void {
		if ( !empty( $response['payment_method'] ) ) {
			if ( isset( $response['payment_method']['id'] ) ) {
				$result['recurring_payment_token'] = $response['payment_method']['id'];
			}
			$gravyPaymentMethod = $response['payment_method']['method'] ?? '';
			$gravyPaymentSubmethod = $response['payment_method']['scheme'] ?? '';
			[ $normalizedPaymentMethod, $normalizedPaymentSubmethod ] = ReferenceData::decodePaymentMethod( $gravyPaymentMethod, $gravyPaymentSubmethod );
			$result['payment_method'] = $normalizedPaymentMethod;
			$result['payment_submethod'] = $normalizedPaymentSubmethod;

			if ( !empty( $response['payment_method']['approval_url'] ) ) {
				$result['redirect_url'] = $response['payment_method']['approval_url'];
			}
		}
	}

	/**
	 * Maps from gravy payment response donor details
	 * @param array &$result
	 * @param array $response
	 * @return void
	 */
	protected function mapPaymentResponseDonorDetails( array &$result, array $response ): void {
		$gravyPaymentMethod = $response['payment_method']['method'] ?? '';
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
					// if state not set but state_code is set, use state_code
					'state' => $donorAddress['state'] ?? '',
					'city' => $donorAddress['city'] ?? '',
					'country' => $donorAddress['country'] ?? '',
				];

				if ( empty( $donorAddress['state'] ) && !empty( $donorAddress['state_code'] ) ) {
					$result['donor_details']['address']['state'] = str_replace( $donorAddress['country'] . '-', '', $donorAddress['state_code'] );
				}
			}
		}
	}

	/**
	 * Maps from gravy payment response payment service details
	 * @param array &$result
	 * @param array $response
	 * @return void
	 */
	private function mapPaymentResponsePaymentService( array &$result, array $response ): void {
		$result['backend_processor'] = $this->getBackendProcessor( $response );
		$result['backend_processor_transaction_id'] = $response['payment_service_transaction_id'] ?? null;
		$result['payment_orchestrator_reconciliation_id'] = $response['reconciliation_id'] ?? null;
	}

	/**
	 * Normalize Gravy payment response from Gravy format to pick out the key parameters
	 * @param array $response
	 * @return array
	 */
	private function mapSuccessfulPaymentResponse( array $response ): array {
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

		$this->mapPaymentResponsePaymentMethodDetails( $result, $response );
		$this->mapPaymentResponseDonorDetails( $result, $response );

		$this->mapPaymentResponsePaymentService( $result, $response );

		return $result;
	}

	/**
	 * Normalize Gravy payment refund response from Gravy format to pick out the key parameters
	 * @param array $response
	 * @param string $type
	 * @return array
	 */
	protected function mapSuccessfulRefundMessage( array $response, string $type = 'refund' ): array {
		return [
			'is_successful' => true,
			'gateway_parent_id' => $response['transaction_id'] ?? $response['id'],
			'gateway_refund_id' => $response['id'],
			'currency' => $response['currency'],
			'amount' => $response['amount'] / 100,
			'reason' => $response['reason'] ?? '',
			'status' => $this->normalizeStatus( $response['status'] ),
			'raw_status' => $response['status'],
			'type' => $type,
			'raw_response' => $response,
			'backend_processor' => $this->getBackendProcessor( $response ) ?? ''
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
	protected function mapErrorFromResponse( array $error ): array {
		$errorParameters = [
			'code' => '',
			'message' => '',
			'description' => ''
		];
		if ( $error['type'] == 'error' ) {
			$errorParameters['code'] = $error['status'] ?? '';
			$errorParameters['message'] = $error['code'] ?? '';
			$errorParameters['description'] = $error['message'] ?? '';
		} elseif ( ( isset( $error['intent_outcome'] ) && $error['intent_outcome'] == 'failed' )
		// Ensure error details are mapped for failed transaction details coming in through listener
		|| ( isset( $error['status'] ) && $this->normalizeStatus( $error['status'] ) === FinalStatus::FAILED ) ) {
			$errorParameters['code'] = $error['error_code'] ?? '';
			$errorParameters['message'] = $error['status'] ?? '';

			// Only chargeback for specific payment methods
			if ( $this->requiresChargebackIfFailed( $error ) ) {
				// Gravy returns this failed transaction errors with the same structure as successful payments.
				// We can map this failed transactions as a chargeback in the normalized_response
				$errorParameters['normalized_response'] = $this->mapSuccessfulRefundMessage( $error, 'chargeback' );
				$errorParameters['normalized_response']['reason'] = 'Payment failed';
			}
		} else {
			$errorParameters['code'] = $error['error_code'] ?? '';
			$errorParameters['message'] = $error['raw_response_code'] ?? '';
			$errorParameters['description'] = $error['raw_response_description'] ?? '';
		}

		if ( ( isset( $error['three_d_secure'] ) && $error['three_d_secure']['status'] === 'error' ) ) {
			$errorParameters = $this->mapFrom3DSecureErrorResponse( $error['three_d_secure'] );
		}

		$errorCode = ErrorMapper::getError( $errorParameters['code'] );

		$errorResponse = [
			'is_successful' => false,
			'status' => FinalStatus::FAILED,
			'code' => $errorCode,
			'message' => $errorParameters['message'],
			'description' => $errorParameters['description'],
			'raw_response' => $error
		];
		if ( !isset( $errorParameters['normalized_response'] ) ) {
			return $errorResponse;
		}

		return array_merge( $errorParameters['normalized_response'], $errorResponse );
	}

	protected function mapFrom3DSecureErrorResponse( array $params ): array {
		$errorData = $params['error_data'];
		$error = [
			'code' => $errorData['code'],
			'message' => $errorData['description'],
			'description' => $errorData['detail']
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
			|| ( isset( $response['intent_outcome'] ) && $response['intent_outcome'] === 'failed' )
			// 3d secure errors
			|| ( isset( $response['three_d_secure'] ) && $response['three_d_secure']['status'] === 'error' )
			// Payment errors from the listener
			|| ( isset( $response['status'] ) && $this->normalizeStatus( $response['status'] ) === FinalStatus::FAILED );
	}

	/**
	 * Gets the payment processor used by Gravy for the transaction
	 * @param array $response
	 * @return string|null
	 */
	protected function getBackendProcessor( array $response ): ?string {
		if ( !empty( $response['payment_service'] ) ) {
			if ( !empty( $response['payment_service']['payment_service_definition_id'] ) ) {
				$paymentServiceDefinitionId = $response['payment_service']['payment_service_definition_id'];
				return GravyHelper::extractProcessorNameFromServiceDefinitionId( $paymentServiceDefinitionId );
			}
		}
		return null;
	}

	/**
	 * Some payment method requires a chargeback message when it fails
	 * because they are set to complete status before getting a successful response
	 * @param array $response
	 * @return bool
	 */
	protected function requiresChargebackIfFailed( array $response ): bool {
		if ( $this->getBackendProcessor( $response ) === PaymentMethod::ACH->toGravyValue() ) {
			return true;
		}
		[ $normalizedPaymentMethod, $normalizedPaymentSubmethod ] = ReferenceData::decodePaymentMethod(
			$response['payment_method']['method'] ?? '',
			''
		);

		return $normalizedPaymentMethod === 'dd' && $normalizedPaymentSubmethod === 'ach';
	}
}
