<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCancelPaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreateDonorResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetDonorResponseFactory;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetPaymentDetailsResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Validators\Validator;
use SmashPig\PaymentProviders\ICancelablePaymentProvider;
use SmashPig\PaymentProviders\IDeleteRecurringPaymentTokenProvider;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\ValidationException;

abstract class PaymentProvider implements IPaymentProvider, IDeleteRecurringPaymentTokenProvider, ICancelablePaymentProvider {
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
	public function getPaymentDetails( array $params ) : PaymentDetailResponse {
		$paymentDetailResponse = new PaymentDetailResponse();
		try {
			// extract out the validation of input out to a separate class
			$validator = new Validator();
			$validator->validateGetPaymentDetailsInput( $params );

			$rawGravyGetPaymentDetailResponse = $this->api->getTransaction( $params );

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = new ResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $rawGravyGetPaymentDetailResponse );

			$paymentDetailResponse = GravyGetPaymentDetailsResponseFactory::fromNormalizedResponse( $normalizedResponse );
		}  catch ( ValidationException $e ) {
			// it threw an exception!
			GravyGetPaymentDetailsResponseFactory::handleValidationException( $paymentDetailResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Failed to get payment details, response: ' . $e->getMessage() );
			GravyGetPaymentDetailsResponseFactory::handleException( $paymentDetailResponse, $e->getMessage(), $e->getCode() );
		}

		return $paymentDetailResponse;
	}

	public function cancelPayment( string $gatewayTxnId ) : CancelPaymentResponse {
		// create our standard response object from the normalized response
		$cancelPaymentResponse = new CancelPaymentResponse();
		try {
			$rawGravyGetPaymentDetailResponse = $this->api->cancelTransaction( $gatewayTxnId );

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = new ResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $rawGravyGetPaymentDetailResponse );

			$cancelPaymentResponse = GravyCancelPaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Processor failed to cancel transaction:' . $e->getMessage() );
			GravyCancelPaymentResponseFactory::handleException( $cancelPaymentResponse, $e->getMessage(), $e->getCode() );
		}

		return $cancelPaymentResponse;
	}

	public function getDonorRecord( array $params ) : PaymentDetailResponse {
		$donorResponse = new PaymentDetailResponse();
		try {
			// extract out the validation of input out to a separate class
			$validator = new Validator();
			$validator->validateDonorInput( $params );
			// map local params to external format, ideally only changing key names and minor input format transformations
			$gravyRequestMapper = new RequestMapper();
			$gravyGetDonorRequest = $gravyRequestMapper->mapToGetDonorRequest( $params );

			$rawGravyGetDonorResponse = $this->api->getDonor( $gravyGetDonorRequest );

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = new ResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromGetDonorResponse( $rawGravyGetDonorResponse );

			$donorResponse = GravyGetDonorResponseFactory::fromNormalizedResponse( $normalizedResponse );
		}  catch ( ValidationException $e ) {
			// it threw an exception!
			GravyGetDonorResponseFactory::handleValidationException( $donorResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Processor failed to get Donor with response:' . $e->getMessage() );
			GravyGetDonorResponseFactory::handleException( $donorResponse, $e->getMessage(), $e->getCode() );
		}

		return $donorResponse;
	}

	public function createDonor( array $params ) : PaymentDetailResponse {
		$donorResponse = new PaymentDetailResponse();
		try {
			// extract out the validation of input out to a separate class
			$validator = new Validator();
			$validator->validateCreateDonorInput( $params );

			// map local params to external format, ideally only changing key names and minor input format transformations
			$gravyRequestMapper = new RequestMapper();
			$gravyCreateDonorRequest = $gravyRequestMapper->mapToCreateDonorRequest( $params );

			$rawGravyCreateDonorResponse = $this->api->createDonor( $gravyCreateDonorRequest );

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = new ResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromCreateDonorResponse( $rawGravyCreateDonorResponse );

			$donorResponse = GravyCreateDonorResponseFactory::fromNormalizedResponse( $normalizedResponse );
		}  catch ( ValidationException $e ) {
			// it threw an exception!
			GravyCreateDonorResponseFactory::handleValidationException( $donorResponse, $e->getData() );
		} catch ( \Exception $e ) {
			// it threw an exception!
			Logger::error( 'Processor failed to create new Donor with response:' . $e->getMessage() );
			GravyCreateDonorResponseFactory::handleException( $donorResponse, $e->getMessage(), $e->getCode() );
		}

		return $donorResponse;
	}

	public function deleteRecurringPaymentToken( array $params ): bool {
		$response = false;
		try {
			$validator = new Validator();
			$validator->validateDeletePaymentTokenInput( $params );

			$gravyRequestMapper = new RequestMapper();
			$gravyDeleteToken = $gravyRequestMapper->mapToDeletePaymentTokenRequest( $params );

			$rawGravyDeletePaymentTokenResponse = $this->api->deletePaymentToken( $gravyDeleteToken );

			// map the response from the external format back to our normalized structure.
			$gravyResponseMapper = new ResponseMapper();
			$normalizedResponse = $gravyResponseMapper->mapFromDeletePaymentTokenResponse( $rawGravyDeletePaymentTokenResponse );

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

	protected function setProcessorContactId( &$params ): void {
		if ( !isset( $params['processor_contact_id'] ) ) {
			$processorContact = $this->getDonorRecord( $params );
			if ( !$processorContact->isSuccessful() ) {
				Logger::info( 'Creating new donor record on Gr4vy with the following parameters:' . json_encode( $params ) );
				$processorContact = $this->createDonor( $params );
			}
			if ( !$processorContact->isSuccessful() ) {
				Logger::error( 'Processor failed to create new contact record with error response:' . json_encode( $processorContact->getRawResponse() ) );
				if ( count( $processorContact->getErrors() ) > 0 ) {
					$error = $processorContact->getErrors()[0];
					throw new \Exception( $error->getDebugMessage(), $error->getErrorCode() );
				} else {
					throw new \Exception( "Unknown Error when creating donor record on Processor", ErrorCode::UNKNOWN );
				}
			}
			$processorContactRecord = $processorContact->getDonorDetails();
			$params['processor_contact_id'] = $processorContactRecord->getCustomerId();
		}
	}

}
