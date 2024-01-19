<?php

namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;
use Psr\Log\LogLevel;
use SmashPig\Core\Cache\CacheHelper;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;
use SmashPig\PaymentData\SavedPaymentDetails;
use SmashPig\PaymentData\StatusNormalizer;
use SmashPig\PaymentProviders\ICancelablePaymentProvider;
use SmashPig\PaymentProviders\ICancelAutoRescueProvider;
use SmashPig\PaymentProviders\IDeleteDataProvider;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\IRefundablePaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CancelAutoRescueResponse;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\DeleteDataResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\PaymentProviders\Responses\PaymentMethodResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;
use SmashPig\PaymentProviders\Responses\SavedPaymentDetailsResponse;
use SmashPig\PaymentProviders\RiskScorer;

/**
 * Class PaymentProvider
 * @package SmashPig\PaymentProviders\Adyen
 *
 *
 */
abstract class PaymentProvider implements
	ICancelablePaymentProvider,
	IDeleteDataProvider,
	IPaymentProvider,
	IRefundablePaymentProvider,
	IGetLatestPaymentStatusProvider,
	ICancelAutoRescueProvider
{
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
		$badParams = $this->validateGetPaymentMethodsParams( $params );
		if ( count( $badParams ) > 0 ) {
			$response = new PaymentMethodResponse();
			$response->setSuccessful( false );

			foreach ( $badParams as $badParam ) {
				$response->addValidationError( new ValidationError( $badParam ) );
			}
			return $response;
		}
		$callback = function () use ( $params ) {
			return $this->api->getPaymentMethods( $params );
		};

		// Not actually varying the cache based on amount, since
		// that would make it a lot less useful and we seem to see
		// the same values regardless of value.
		$cacheKey = $this->cacheParameters['key-base'] . '_'
			. $params['country'] . '_'
			. $params['currency'] . '_'
			. $params['language'];

		$rawResponse = CacheHelper::getWithSetCallback( $cacheKey, $this->cacheParameters['duration'], $callback );
		$response = new PaymentMethodResponse();
		$response->setRawResponse( $rawResponse );
		$response->setSuccessful( true );

		return $response;
	}

	/**
	 * Get the last Adyen status from our payments_initial db table.
	 *
	 * $params['gateway_txn_id'] is required
	 * $params['gateway'] is required
	 * $params['order_id'] is required
	 * $params['recurring_payment_token'] is optional
	 *
	 * Note: Adyen doesn't offer an API action to retrieve this info so
	 * we're using the last status we saved. If no final status is set then
	 * we return pending-poke.
	 *
	 * @param array $params
	 * @return PaymentDetailResponse
	 */
	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse {
		$response = new PaymentDetailResponse();
		$response->setGatewayTxnId( $params['gateway_txn_id'] );
		$response->setRecurringPaymentToken( $params['recurring_payment_token'] ?? '' );
		// will check the breakdown at resolve again, so it's fine to be blank
		$response->setRiskScores( [] );
		$this->paymentsInitialDatabase = PaymentsInitialDatabase::get();
		$paymentsInitRow = $this->paymentsInitialDatabase->fetchMessageByGatewayOrderId(
			$params['gateway'], $params['order_id']
		);
		$response->setStatus( $paymentsInitRow['payments_final_status'] ?? FinalStatus::PENDING_POKE );
		// successful if either FinalStatus::PENDING_POKE, FinalStatus::COMPLETE
		$response->setSuccessful( in_array( $response->getStatus(), $this->getPaymentDetailsSuccessfulStatuses() ) );
		return $response;
	}

	/**
	 * Get more payment details from the redirect result
	 *
	 * @param string $redirectResult
	 * @return PaymentDetailResponse
	 */
	public function getHostedPaymentDetails( $redirectResult ): PaymentDetailResponse {
		$rawResponse = $this->api->getPaymentDetails( $redirectResult );

		$response = new PaymentDetailResponse();
		// TODO: DRY with CreatePaymentResponse
		$response->setRawResponse( $rawResponse );
		$rawStatus = $rawResponse['resultCode'];
		$this->mapStatus(
			$response,
			$rawResponse,
			$this->getPaymentDetailsStatusNormalizer(),
			$rawStatus,
			$this->getPaymentDetailsSuccessfulStatuses()
		);
		if ( isset( $rawResponse['additionalData'] ) ) {
			$this->mapAdditionalData( $rawResponse['additionalData'], $response );
		}
		$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );
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
		// The API call we were using before had emails for each list item, while API
		// call we're using now just seems to have this on the root node.
		$email = $rawResponse['lastKnownShopperEmail'] ?? null;
		$detailsList = [];
		foreach ( $rawResponse['details'] as $detail ) {
			$storedMethod = $detail['RecurringDetail'];
			$paymentDetailsObject = new SavedPaymentDetails();
			// Set generic properties
			$paymentDetailsObject
				->setToken( $storedMethod['recurringDetailReference'] )
				->setDisplayName( $storedMethod['name'] ?? null )
				->setOwnerEmail( $storedMethod['shopperEmail'] ?? $email ?? null );

			if ( isset( $storedMethod['variant'] ) ) {
				try {
					[ $method, $submethod ] = ReferenceData::decodePaymentMethod(
						$storedMethod['variant'], $storedMethod['paymentMethodVariant'] ?? false
					);
					$paymentDetailsObject
						->setPaymentMethod( $method )
						->setPaymentSubmethod( $submethod );
				}
				catch ( OutOfBoundsException $ex ) {
					// No big deal for us if we don't have it mapped - the token field
					// is the only one we actually use.
				}
			}

			if ( isset( $storedMethod['bank'] ) ) {
				// Set properties for bank transfers, e.g. iDEAL
				$paymentDetailsObject
					->setOwnerName( $storedMethod['bank']['ownerName'] ?? null )
					->setIban( $storedMethod['bank']['iban'] ?? null );
			} elseif ( isset( $storedMethod['card'] ) ) {
				// Set properties for tokenized credit cards & Apple/Google Pay
				$paymentDetailsObject->setOwnerName( $storedMethod['card']['holderName'] ?? null )
					->setExpirationMonth( $storedMethod['card']['expiryMonth'] ?? null )
					->setExpirationYear( $storedMethod['card']['expiryYear'] ?? null )
					->setCardSummary( $storedMethod['card']['number'] ?? null );
			}

			$detailsList[] = $paymentDetailsObject;
		}
		$response->setDetailsList( $detailsList );
		$response->setSuccessful( count( $detailsList ) > 0 );

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
			$response->setSuccessful( false );
			Logger::debug( $responseError, $rawResponse );
		} else {
			$this->mapStatus(
				$response,
				$rawResponse,
				new ApprovePaymentStatus(),
				$rawResponse['status'],
				[ FinalStatus::COMPLETE ]
			);
		}
		$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );
		return $response;
	}

	/**
	 * Refunds a payment
	 * https://docs.adyen.com/online-payments/refund
	 *
	 * @param array $params
	 * @return RefundPaymentResponse
	 */
	public function refundPayment( array $params ): RefundPaymentResponse {
		$rawResponse = $this->api->refundPayment( $params );
		$response = new RefundPaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( empty( $rawResponse['status'] ) ) {
			$responseError = 'status element missing from Adyen capture response.';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$responseError,
				LogLevel::ERROR
			) );
			$response->setSuccessful( false );
			Logger::debug( $responseError, $rawResponse );
		} else {
			$this->mapStatus(
				$response,
				$rawResponse,
				new RefundPaymentStatus(),
				$rawResponse['status'],
				[ FinalStatus::COMPLETE ]
			);
		}
		$this->mapGatewayTxnIdAndErrors( $response, $rawResponse );
		return $response;
	}

	/**
	 * Cancels a payment
	 *
	 * @param string $gatewayTxnId
	 * @return CancelPaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelPayment( $gatewayTxnId ): CancelPaymentResponse {
		$rawResponse = $this->api->cancel( $gatewayTxnId );
		$response = new CancelPaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( empty( $rawResponse['status'] ) ) {
			$responseError = 'cancelResult element missing from Adyen cancel response.';
			$response->addErrors(
				new PaymentError(
					ErrorCode::MISSING_REQUIRED_DATA,
					$responseError,
					LogLevel::ERROR
				)
			);
			$response->setSuccessful( false );
			Logger::debug( $responseError, $rawResponse );
		} else {
			$this->mapStatus(
				$response,
				$rawResponse,
				new CancelPaymentStatus(),
				$rawResponse['status'],
				[ FinalStatus::CANCELLED ]
			);
		}
		$this->mapGatewayTxnIdAndErrors(
			$response,
			$rawResponse
		);

		return $response;
	}

	/**
	 * Cancel auto rescue when donor cancels recurring donation in Civi
	 *
	 * @param string $rescueReference
	 * @return CancelAutoRescueResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelAutoRescue( $rescueReference ) : CancelAutoRescueResponse {
		$rawResponse = $this->api->cancelAutoRescue( $rescueReference );
		$response = new CancelAutoRescueResponse();
		$response->setRawResponse( $rawResponse );

		if ( empty( $rawResponse['response'] ) || $rawResponse['response'] !== '[cancel-received]' ) {
			$responseError = 'cancel auto rescue request is not received';
			$response->addErrors(
				new PaymentError(
					ErrorCode::MISSING_REQUIRED_DATA,
					$responseError,
					LogLevel::ERROR
				)
			);
			$response->setSuccessful( false );
			Logger::debug( $responseError, $rawResponse );
		} else {
			$response->setSuccessful( true );
			// This PSP reference associated with this cancel request not the original one, but save for potential future use
			$response->setGatewayTxnId( $rawResponse['pspReference'] );
		}

		return $response;
	}

	/**
	 * Request deletion of all donor data associated with a payment.
	 *
	 * @param string $gatewayTransactionId called the PSP reference by Adyen
	 * @return DeleteDataResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function deleteDataForPayment( string $gatewayTransactionId ): DeleteDataResponse {
		$rawResponse = $this->api->deleteDataForPayment( $gatewayTransactionId );
		$response = new DeleteDataResponse();
		$response->setRawResponse( $rawResponse );
		if ( !isset( $rawResponse['result'] ) ) {
			$responseError = 'Adyen response was null or invalid JSON.';
			$response->addErrors( new PaymentError(
				ErrorCode::MISSING_REQUIRED_DATA,
				$responseError,
				LogLevel::ERROR
			) );
			$response->setSuccessful( false );
		} else {
			switch ( $rawResponse['result'] ) {
				case 'SUCCESS':
				case 'ALREADY_PROCESSED':
					$response->setSuccessful( true );
					break;
				case 'ACTIVE_RECURRING_TOKEN_EXISTS':
					// shouldn't get here, since we're always passing forceErasure: true
					$errorMessage = 'Adyen data deletion request failed on active recurring token';
					$response->addErrors( new PaymentError(
							ErrorCode::UNKNOWN,
							$errorMessage,
							LogLevel::ERROR
						)
					);
					// Log here too just because it's so odd
					Logger::error( $errorMessage );
					$response->setSuccessful( false );
					break;
				case 'PAYMENT_NOT_FOUND':
					$response->setSuccessful( false );
					$response->addErrors( new PaymentError(
							ErrorCode::TRANSACTION_NOT_FOUND,
							"PSP reference $gatewayTransactionId not found at Adyen",
							LogLevel::ERROR
						)
					);
					break;
			}
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
	protected function mapGatewayTxnIdAndErrors(
		PaymentProviderResponse $response,
		?array $rawResponse
	) : void {
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
	 * @param array $successfulStatuses Which of the normalized statuses should result in isSuccessful = true
	 */
	protected function mapStatus(
		PaymentProviderResponse $response,
		$rawResponse,
		StatusNormalizer $statusMapper,
		$rawStatus,
		array $successfulStatuses = [ FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ]
	) {
		if ( !empty( $rawStatus ) ) {
			$response->setRawStatus( $rawStatus );
			try {
				$status = $statusMapper->normalizeStatus( $rawStatus );
				$response->setStatus( $status );
				$success = in_array( $status, $successfulStatuses );
				$response->setSuccessful( $success );
			} catch ( \Exception $ex ) {
				$response->addErrors( new PaymentError(
					ErrorCode::UNEXPECTED_VALUE,
					$ex->getMessage(),
					LogLevel::ERROR
				) );
				$response->setSuccessful( false );
				Logger::debug( 'Unable to map Adyen status', $rawResponse );
			}
		} else {
			if ( $response->hasErrors() ) {
				// We don't necessarily get a status code if there's another error
				// but it sure as heck didn't succeed!
				$response->setStatus( FinalStatus::FAILED );
			} else {
				$message = 'Missing Adyen status';
				$response->addErrors(
					new PaymentError(
						ErrorCode::MISSING_REQUIRED_DATA,
						$message,
						LogLevel::ERROR
					)
				);
				Logger::debug( $message, $rawResponse );
			}
			$response->setSuccessful( false );
		}
	}

	abstract protected function getPaymentDetailsStatusNormalizer(): StatusNormalizer;

	abstract protected function getPaymentDetailsSuccessfulStatuses(): array;

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

	/**
	 * The only required param is country
	 * @param array $params
	 * @return array
	 */
	protected function validateGetPaymentMethodsParams( array $params ): array {
		$badParams = [];
		// FIXME: we should move the supported country/currency yaml files to the SmashPig level
		// and use those, rather than these possibly-incomplete lists.
		if ( !array_key_exists( $params['country'], NationalCurrencies::getNationalCurrencies() ) ) {
			$badParams[] = 'country';
		}
		if ( isset( $params['currency'] ) && !array_key_exists( $params['currency'], CurrencyRates::getCurrencyRates() ) ) {
			$badParams[] = 'currency';
		}
		if ( isset( $params['amount'] ) && !is_numeric( $params['amount'] ) ) {
			$badParams[] = 'amount';
		}
		if ( isset( $params['language'] ) && empty( $params['language'] ) ) {
			$badParams[] = 'language';
		}
		return $badParams;
	}
}
