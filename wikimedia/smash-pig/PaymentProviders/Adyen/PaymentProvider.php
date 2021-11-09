<?php

namespace SmashPig\PaymentProviders\Adyen;

use Psr\Log\LogLevel;
use SmashPig\Core\Cache\CacheHelper;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\ApprovePaymentResponse;
use SmashPig\PaymentProviders\CancelPaymentResponse;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentDetailResponse;
use SmashPig\PaymentProviders\PaymentMethodResponse;
use SmashPig\PaymentProviders\PaymentProviderResponse;
use SmashPig\PaymentProviders\RiskScorer;
use SmashPig\PaymentProviders\SavedPaymentDetails;
use SmashPig\PaymentProviders\SavedPaymentDetailsResponse;

/**
 * Class PaymentProvider
 * @package SmashPig\PaymentProviders\Adyen
 *
 *
 */
abstract class PaymentProvider implements IPaymentProvider {
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * @var \SmashPig\Core\ProviderConfiguration
	 */
	protected $providerConfiguration;

	/**
	 * @var array
	 */
	protected $cacheParameters;

	public function __construct( array $options ) {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
		$this->cacheParameters = $options['cache-parameters'];
	}

	/**
	 * Gets available payment methods
	 *
	 * @param array $params
	 * @return PaymentMethodResponse
	 */
	public function getPaymentMethods( array $params ) : PaymentMethodResponse {
		$callback = function () use ( $params ) {
			$rawResponse = $this->api->getPaymentMethods( $params );

			$response = new PaymentMethodResponse();
			$response->setRawResponse( $rawResponse );

			return $response;
		};
		// Not actually varying the cache based on amount, since
		// that would make it a lot less useful and we seem to see
		// the same values regardless of value.
		$cacheKey = $this->cacheParameters['key-base'] . '_'
			. $params['country'] . '_'
			. $params['currency'] . '_'
			. $params['language'];

		return CacheHelper::getWithSetCallback( $cacheKey, $this->cacheParameters['duration'], $callback );
	}

	/**
	 * Get more payment details from the redirect result
	 *
	 * @param string $redirectResult
	 * @return PaymentDetailResponse
	 */
	public function getHostedPaymentDetails( $redirectResult ) {
		$rawResponse = $this->api->getPaymentDetails( $redirectResult );

		$response = new PaymentDetailResponse();
		// TODO: DRY with CreatePaymentResponse
		$response->setRawResponse( $rawResponse );
		$rawStatus = $rawResponse['resultCode'];
		$this->mapStatus(
			$response,
			$rawResponse,
			$this->getPaymentDetailsStatusNormalizer(),
			$rawStatus
		);
		if ( isset( $rawResponse['additionalData'] ) ) {
			$this->mapAdditionalData( $rawResponse['additionalData'], $response );
		}
		$this->mapRestIdAndErrors( $response, $rawResponse );
		return $response;
	}

	/**
	 * Get details of payment methods on file for a specified donor
	 * This uses the same API call as getting payment methods but also returns
	 * the saved payment method details for the shopperReference provided
	 *
	 * @param string $processorContactID
	 * @return SavedPaymentDetailsResponse
	 */
	public function getSavedPaymentDetails( string $processorContactID ): SavedPaymentDetailsResponse {
		$rawResponse = $this->api->getSavedPaymentDetails( $processorContactID );
		$response = new SavedPaymentDetailsResponse();
		$response->setRawResponse( $rawResponse );
		$detailsList = [];
		foreach ( $rawResponse['storedPaymentMethods'] as $storedMethod ) {
			$ownerName = $storedMethod['ownerName'] ?? $storedMethod['holderName'] ?? null;
			if ( isset( $storedMethod['brand'] ) ) {
				[ $method, $submethod ] = ReferenceData::decodePaymentMethod(
					$storedMethod['brand'], false
				);
			} elseif ( $storedMethod['type'] === 'sepadirectdebit' ) {
				// special case, for now we only use SEPA direct debit for iDEAL
				$method = 'rtbt';
				$submethod = 'rtbt_ideal';
			} else {
				// No big deal for us if we don't have it mapped - the token field
				// is the only one we actually use.
				$method = null;
				$submethod = null;
			}
			$detailsList[] = ( new SavedPaymentDetails() )
				->setToken( $storedMethod['id'] )
				->setDisplayName( $storedMethod['name'] ?? null )
				->setPaymentMethod( $method )
				->setPaymentSubmethod( $submethod )
				->setExpirationMonth( $storedMethod['expiryMonth'] ?? null )
				->setExpirationYear( $storedMethod['expiryYear'] ?? null )
				->setOwnerName( $ownerName )
				->setOwnerEmail( $storedMethod['shopperEmail'] ?? null )
				->setIban( $storedMethod['iban'] ?? null )
				->setCardSummary( $storedMethod['lastFour'] ?? null );
		}
		$response->setDetailsList( $detailsList );

		return $response;
	}

	/**
	 * Approves a payment
	 * FIXME: Should probably put this on a separate interface from IPaymentProvider.
	 * Leaving this on the base class for now since subclasses need
	 * an implementation and DirectDebit doesn't have one.
	 *
	 * @param array $params
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		$rawResponse = $this->api->approvePayment( $params );
		$response = new ApprovePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( empty( $rawResponse['status'] ) ) {
			$responseError = 'status element missing from Adyen capture response.';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$responseError,
				LogLevel::ERROR
			) );
			Logger::debug( $responseError, $rawResponse );
		} else {
			$this->mapStatus(
				$response,
				$rawResponse,
				new ApprovePaymentStatus(),
				$rawResponse['status']
			);
		}
		$this->mapRestIdAndErrors( $response, $rawResponse );
		return $response;
	}

	/**
	 * Cancels a payment
	 *
	 * @param string $gatewayTxnId
	 * @return CancelPaymentResponse
	 */
	public function cancelPayment( $gatewayTxnId ) {
		$rawResponse = $this->api->cancel( $gatewayTxnId );
		$response = new CancelPaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse->cancelResult ) ) {
			$this->mapTxnIdAndErrors(
				$response,
				$rawResponse->cancelResult,
				false
			);
			$this->mapStatus(
				$response,
				$rawResponse,
				new CancelPaymentStatus(),
				$rawResponse->cancelResult->response ?? null
			);
		} else {
			$responseError = 'cancelResult element missing from Adyen cancel response.';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$responseError,
				LogLevel::ERROR
			) );
			Logger::debug( $responseError, $rawResponse );
		}

		return $response;
	}

	/**
	 * Maps a couple of common properties of Adyen Checkout API responses to our
	 * standardized PaymentProviderResponse.
	 * Their pspReference is mapped to our GatewayTxnId and their refusalReason
	 * is mapped to a PaymentError with a normalized ErrorCode
	 * TODO: some refusalReasons should get ValidationError not PaymentError
	 *
	 * @param PaymentProviderResponse $response
	 * @param ?array $rawResponse
	 */
	protected function mapRestIdAndErrors(
		PaymentProviderResponse $response,
		?array $rawResponse
	) {
		if ( $rawResponse === null ) {
			$responseError = 'Adyen response was null or invalid JSON.';
			$response->addErrors( new PaymentError(
				ErrorCode::NO_RESPONSE,
				$responseError,
				LogLevel::ERROR
			) );
			Logger::debug( $responseError, $rawResponse );
		} else {
			// Map trxn id if present. Redirect responses won't have this
			// yet, so no need to throw an error when this is empty.
			if ( !empty( $rawResponse['pspReference'] ) ) {
				$response->setGatewayTxnId( $rawResponse['pspReference'] );
			}
			if ( !empty( $rawResponse['errorCode'] ) ) {
				$badField = ValidationErrorMapper::getValidationErrorField( $rawResponse['errorCode'] );
				if ( $badField !== null ) {
					$response->addValidationError( new ValidationError( $badField ) );
				}
			}
			// Map refusal reason to PaymentError
			if ( !empty( $rawResponse['refusalReason'] ) ) {
				if ( $this->canRetryRefusalReason( $rawResponse['refusalReason'] ) ) {
					$errorCode = ErrorCode::DECLINED;
				} else {
					$errorCode = ErrorCode::DECLINED_DO_NOT_RETRY;
				}
				$response->addErrors(
					new PaymentError(
						$errorCode,
						$rawResponse['refusalReason'],
						LogLevel::INFO
					)
				);
			}
		}
	}

	/**
	 * Maps gateway transaction ID and errors from $rawResponse to $response. The replies we get back from the
	 * Adyen API have a section with 'pspReference' and 'refusalReason' properties. Exactly where this section
	 * is depends on the API call, but we map them all the same way.
	 *
	 * @param PaymentProviderResponse $response An instance of a PaymentProviderResponse subclass to be populated
	 * @param object $rawResponse The bit of the API response that has pspReference and refusalReason
	 * @param bool $checkForRetry Whether to test the refusalReason against a list of retryable reasons.
	 */
	protected function mapTxnIdAndErrors(
		PaymentProviderResponse $response,
		$rawResponse,
		$checkForRetry = true
	) {
		// map trxn id
		if ( !empty( $rawResponse->pspReference ) ) {
			$response->setGatewayTxnId( $rawResponse->pspReference );
		} else {
			$message = 'Unable to map Adyen Gateway Transaction ID';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_TRANSACTION_ID,
				$message,
				LogLevel::ERROR
			) );
			Logger::debug( $message, $rawResponse );
		}
		// map errors
		if ( !empty( $rawResponse->refusalReason ) ) {
			if ( $checkForRetry ) {
				if ( $this->canRetryRefusalReason( $rawResponse->refusalReason ) ) {
					$errorCode = ErrorCode::DECLINED;
				} else {
					$errorCode = ErrorCode::DECLINED_DO_NOT_RETRY;
				}
			} else {
				$errorCode = ErrorCode::UNEXPECTED_VALUE;
			}
			$response->addErrors( new PaymentError(
				$errorCode,
				$rawResponse->refusalReason,
				LogLevel::INFO
			) );
		}
	}

	/**
	 * Normalize the raw status or add appropriate errors to our response object. We have a group of classes
	 * whose function is normalizing raw status codes for specific API calls. We expect SOME status code back
	 * from any API call, so when that is missing we always add a MISSING_REQUIRED_DATA error. Otherwise we
	 * call the mapper and set the appropriate status on our PaymentProviderResponse object. Errors in
	 * normalization result in adding an UNEXPECTED_VALUE error to the PaymentProviderResponse.
	 *
	 * @param PaymentProviderResponse $response An instance of a PaymentProviderResponse subclass to be populated
	 * @param object $rawResponse The raw API response object, used to log errors.
	 * @param StatusNormalizer $statusMapper An instance of the appropriate status mapper class
	 * @param string $rawStatus The status string from the API response, either from 'resultCode' or 'response'
	 */
	protected function mapStatus(
		PaymentProviderResponse $response,
		$rawResponse,
		StatusNormalizer $statusMapper,
		$rawStatus
	) {
		if ( !empty( $rawStatus ) ) {
			$response->setRawStatus( $rawStatus );
			try {
				$status = $statusMapper->normalizeStatus( $rawStatus );
				$response->setStatus( $status );
			} catch ( \Exception $ex ) {
				$response->addErrors( new PaymentError(
					ErrorCode::UNEXPECTED_VALUE,
					$ex->getMessage(),
					LogLevel::ERROR
				) );
				Logger::debug( 'Unable to map Adyen status', $rawResponse );
			}
		} else {
			$message = 'Missing Adyen status';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$message,
				LogLevel::ERROR
			) );
			Logger::debug( $message, $rawResponse );
		}
	}

	abstract protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer;

	/**
	 * Documented at
	 * https://docs.adyen.com/development-resources/refusal-reasons
	 *
	 * @param string $refusalReason
	 * @return bool
	 */
	private function canRetryRefusalReason( $refusalReason ) {
		// They may prefix the refusal reason with a numeric code
		$trimmedReason = preg_replace( '/^[0-9:]+ /', '', $refusalReason );
		$noRetryReasons = [
			'Acquirer Fraud',
			'Blocked Card',
			'FRAUD',
			'FRAUD-CANCELLED',
			'Invalid Amount',
			'Invalid Card Number',
			'Invalid Pin',
			'No Contract Found',
			'Pin validation not possible',
			'Referral',
			'Restricted Card',
			'Revocation Of Auth',
			'Issuer Suspected Fraud',
		];
		if ( in_array( $trimmedReason, $noRetryReasons ) ) {
			return false;
		}
		return true;
	}

	protected function mapAdditionalData( array $additionalData, PaymentDetailResponse $response ) {
		$response->setRiskScores(
			( new RiskScorer() )->getRiskScores(
				$additionalData['avsResult'] ?? null,
				$additionalData['cvcResult'] ?? null
			)
		);
		// Recurring payments will send back the token in recurringDetailReference and the processor_contact_id
		// in shopperReference, both are needed to charge a recurring payment
		if ( isset( $additionalData['recurring.shopperReference'] ) ) {
			$response->setProcessorContactID(
				$additionalData['recurring.shopperReference']
			);
		}
		if ( isset( $additionalData['recurring.recurringDetailReference'] ) ) {
			$response->setRecurringPaymentToken(
				$additionalData['recurring.recurringDetailReference']
			);
		}
	}
}
