<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Factories\GravyApprovePaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCancelPaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetLatestPaymentStatusResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyRefundResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyReportResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\CardPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Responses\ReportResponse;
use SmashPig\PaymentProviders\Gravy\Validators\CardPaymentProviderValidator;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;
use SmashPig\PaymentProviders\ICancelablePaymentProvider;
use SmashPig\PaymentProviders\IDeleteRecurringPaymentTokenProvider;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\IRefundablePaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;
use SmashPig\PaymentProviders\ValidationException;

abstract class PaymentProvider implements IPaymentProvider, IDeleteRecurringPaymentTokenProvider, ICancelablePaymentProvider, IRefundablePaymentProvider, IGetLatestPaymentStatusProvider {
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

	/**
	 * @param array $params
	 * @return PaymentDetailResponse
	 */
	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse {
		$paymentDetailResponse = new PaymentDetailResponse();
		try {
			$this->getValidator()->validateGetLatestPaymentStatusInput( $params );
			// dispatch api call to external API
			$rawGravyGetPaymentDetailResponse = $this->api->getTransaction( $params );
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromPaymentResponse( $rawGravyGetPaymentDetailResponse );
			$paymentDetailResponse = GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyGetLatestPaymentStatusResponseFactory::handleValidationException( $paymentDetailResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Failed to get payment details, response: ' . $e->getMessage() );
			GravyGetLatestPaymentStatusResponseFactory::handleException( $paymentDetailResponse, $e->getMessage(), $e->getCode() );
		}

		return $paymentDetailResponse;
	}

	public function cancelPayment( string $gatewayTxnId ): CancelPaymentResponse {
		$cancelPaymentResponse = new CancelPaymentResponse();
		try {
			// dispatch api call to external API
			$rawGravyGetPaymentDetailResponse = $this->api->cancelTransaction( $gatewayTxnId );
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromPaymentResponse( $rawGravyGetPaymentDetailResponse );
			$cancelPaymentResponse = GravyCancelPaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Processor failed to cancel transaction:' . $e->getMessage() );
			GravyCancelPaymentResponseFactory::handleException( $cancelPaymentResponse, $e->getMessage(), $e->getCode() );
		}

		return $cancelPaymentResponse;
	}

	public function deleteRecurringPaymentToken( array $params ): bool {
		$response = false;
		try {
			$this->getValidator()->validateDeletePaymentTokenInput( $params );
			$gravyDeleteToken = $this->getRequestMapper()->mapToDeletePaymentTokenRequest( $params );
			// dispatch api call to external API
			$rawGravyDeletePaymentTokenResponse = $this->api->deletePaymentToken( $gravyDeleteToken );
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromDeletePaymentTokenResponse( $rawGravyDeletePaymentTokenResponse );

			if ( !$normalizedResponse['is_successful'] ) {
				Logger::error( 'Processor failed to delete recurring token with response:' . $normalizedResponse['code'] . ', ' . $normalizedResponse['description'] );
				return $response;
			}
			$response = true;
		} catch ( ValidationException $e ) {
			// it threw an exception!
			Logger::error( 'Missing required data' . json_encode( $e->getData() ) );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Processor failed to delete recurring token with response:' . $e->getMessage() );
		}
		return $response;
	}

	public function refundPayment( array $params ): RefundPaymentResponse {
		$refundResponse = new RefundPaymentResponse();
		try {
			$this->getValidator()->validateRefundInput( $params );
			// dispatch api call to external API
			$gravyRefundRequest = $this->getRequestMapper()->mapToRefundPaymentRequest( $params );
			$rawGravyRefundResponse = $this->api->refundTransaction( $gravyRefundRequest );
			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = $this->getResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromRefundPaymentResponse( $rawGravyRefundResponse );
			$refundResponse = GravyRefundResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyRefundResponseFactory::handleValidationException( $refundResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Processor failed to refund transaction with response:' . $e->getMessage() );
			GravyRefundResponseFactory::handleException( $refundResponse, $e->getMessage(), $e->getCode() );
		}
		return $refundResponse;
	}

	public function getRefundDetails( array $params ): RefundPaymentResponse {
		$refundResponse = new RefundPaymentResponse();
		try {
			$this->getValidator()->validateGetRefundInput( $params );
			// dispatch api call to external API
			$rawGravyRefundResponse = $this->api->getRefund( $params );
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromRefundPaymentResponse( $rawGravyRefundResponse );
			$refundResponse = GravyRefundResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyRefundResponseFactory::handleValidationException( $refundResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( "Processor failed to fetch refund with refund id {$params['gateway_refund_id']}. returned response:" . $e->getMessage() );
			GravyRefundResponseFactory::handleException( $refundResponse, $e->getMessage(), $e->getCode() );
		}
		return $refundResponse;
	}

	public function getReportExecutionDetails( array $params ): ReportResponse {
		$reportResponse = new ReportResponse();
		try {
			$this->getValidator()->validateGetReportExecutionInput( $params );
			// dispatch api call to external API
			$rawGravyReportExecutionResponse = $this->api->getReportExecutionDetails( $params );
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromReportExecutionResponse( $rawGravyReportExecutionResponse );
			$reportResponse = GravyReportResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyReportResponseFactory::handleValidationException( $reportResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( "Processor failed to fetch report execution with id {$params['report_execution_id']}. returned response:" . $e->getMessage() );
			GravyReportResponseFactory::handleException( $reportResponse, $e->getMessage(), $e->getCode() );
		}
		return $reportResponse;
	}

	public function generateReportDownloadUrl( array $params ): ReportResponse {
		$reportResponse = new ReportResponse();
		try {
			$this->getValidator()->validateGenerateReportUrlInput( $params );
			// dispatch api call to external API
			$rawGravyReportDownloadResponse = $this->api->generateReportDownloadUrl( $params );
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromGenerateReportUrlResponse( $rawGravyReportDownloadResponse );
			$reportResponse = GravyReportResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyReportResponseFactory::handleValidationException( $reportResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( "Processor failed to fetch report execution with id {$params['report_execution_id']}. returned response:" . $e->getMessage() );
			GravyReportResponseFactory::handleException( $reportResponse, $e->getMessage(), $e->getCode() );
		}
		return $reportResponse;
	}

	public function createPayment( array $params ): CreatePaymentResponse {
		$createPaymentResponse = new createPaymentResponse();
		try {
			$this->getValidator()->validateCreatePaymentInput( $params );
			$gravyCreatePaymentRequest = $this->getRequestMapper()->mapToCreatePaymentRequest( $params );
			// dispatch api call to external API using mapped params
			$rawGravyCreatePaymentResponse = $this->api->createPayment( $gravyCreatePaymentRequest );
			// normalize gravy response
			$normalizedResponse = $this->getResponseMapper()->mapFromPaymentResponse( $rawGravyCreatePaymentResponse );
			$createPaymentResponse = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyCreatePaymentResponseFactory::handleValidationException( $createPaymentResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception that isn't validation!
			Logger::error( 'Processor failed to create new payment with response:' . $e->getMessage() );
			GravyCreatePaymentResponseFactory::handleException( $createPaymentResponse, $e->getMessage(), $e->getCode() );
		}

		return $createPaymentResponse;
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		$approvePaymentResponse = new ApprovePaymentResponse();
		try {
			$this->getValidator()->validateApprovePaymentInput( $params );
			// map local params to external format, ideally only changing key names and minor input format transformations
			$gravyApprovePaymentRequest = $this->getRequestMapper()->mapToApprovePaymentRequest( $params );
			// dispatch api call to external API using mapped params
			$rawGravyApprovePaymentResponse = $this->api->approvePayment( $params['gateway_txn_id'], $gravyApprovePaymentRequest );
			// map the response from the external format back to our normalized structure.
			$normalizedResponse = $this->getResponseMapper()->mapFromPaymentResponse( $rawGravyApprovePaymentResponse );
			$approvePaymentResponse = GravyApprovePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( ValidationException $e ) {
			// it threw an exception!
			GravyApprovePaymentResponseFactory::handleValidationException( $approvePaymentResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::info( 'Processor failed to approve payment with response:' . $e->getMessage() );
			GravyApprovePaymentResponseFactory::handleException( $approvePaymentResponse, $e->getMessage(), $e->getCode() );
		}

		return $approvePaymentResponse;
	}

	protected function getResponseMapper(): ResponseMapper {
		return new ResponseMapper();
	}

	protected function getRequestMapper(): RequestMapper {
		return new CardPaymentProviderRequestMapper();
	}

	protected function getValidator(): PaymentProviderValidator {
		return new CardPaymentProviderValidator();
	}
}
