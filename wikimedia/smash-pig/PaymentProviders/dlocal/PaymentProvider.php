<?php

namespace SmashPig\PaymentProviders\dlocal;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class PaymentProvider {
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * @var \SmashPig\Core\ProviderConfiguration
	 */
	protected $providerConfiguration;

	public function __construct() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	protected function getCreatePaymentStatusNormalizer() {
		return new CreatePaymentStatus();
	}

	protected function mapStatusAndAddErrorsIfAny(
			PaymentProviderResponse $response,
			array $successfulStatuses = [ FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ]
	): void {
		$statusMapper = $this->getCreatePaymentStatusNormalizer();
		$rawStatus = $response->getRawStatus();
		$rawResponse = $response->getRawResponse();

		if ( $rawStatus ) {
			try {
				$status = $statusMapper->normalizeStatus( $rawStatus );
				$response->setStatus( $status );
				if ( $status === FinalStatus::FAILED ) {
					$response->addErrors( new PaymentError(
							ErrorMapper::$errorCodes[$rawResponse['status_code']],
							$rawResponse['status_detail'],
							LogLevel::ERROR
					) );
				} else {
					$success = in_array( $status, $successfulStatuses );
					$response->setSuccessful( $success );
				}
			} catch ( \Exception $ex ) {
				$response->addErrors( new PaymentError(
						ErrorCode::UNEXPECTED_VALUE,
						$ex->getMessage(),
						LogLevel::ERROR
				) );
				Logger::debug( 'Unable to map dlocal status', $rawResponse );
			}
		} else {
				$message = 'Missing dlocal status';
				$response->addErrors(
						new PaymentError(
								ErrorCode::MISSING_REQUIRED_DATA,
								$message,
								LogLevel::ERROR
						)
				);
				Logger::debug( $message, $rawResponse );
		}

		if ( $response->hasErrors() ) {
			$response->setSuccessful( false );
			$response->setStatus( FinalStatus::FAILED );
		}
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}
}
