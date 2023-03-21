<?php

namespace SmashPig\PaymentProviders\PayPal;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\IGetLatestPaymentStatusProvider;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\IRecurringPaymentProfileProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CancelSubscriptionResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\CreateRecurringPaymentsProfileResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use UnexpectedValueException;

class PaymentProvider implements IPaymentProvider, IGetLatestPaymentStatusProvider, IRecurringPaymentProfileProvider {

	/**
	 * @var Api
	 */
	protected $api;

	public function __construct() {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $providerConfiguration->object( 'api' );
	}

	/**
	 * @inheritDoc
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	/**
	 * Set the PayPal Express Checkout
	 *
	 * @param array $params
	 * @return CreatePaymentSessionResponse
	 */
	public function createPaymentSession( array $params ): CreatePaymentSessionResponse {
		$rawResponse = $this->api->createPaymentSession( $params );

		$response = ( new CreatePaymentSessionResponse() )
			->setRawResponse( $rawResponse )
			->setSuccessful( $this->isSuccessfulPaypalResponse( $rawResponse ) );

		if ( !empty( $rawResponse['ACK'] ) ) {
			$response->setRawStatus( $rawResponse['ACK'] );
		}

		if ( isset( $rawResponse['TOKEN'] ) ) {
			$response->setPaymentSession( $rawResponse['TOKEN'] );
		}

		return $response;
	}

	/**
	 * @param array $params
	 *
	 * @return CreateRecurringPaymentsProfileResponse
	 */
	public function createRecurringPaymentsProfile( array $params ) : CreateRecurringPaymentsProfileResponse {
		$rawResponse = $this->api->createRecurringPaymentsProfile( $params );

		$response = ( new CreateRecurringPaymentsProfileResponse() )
			->setRawResponse( $rawResponse )
			->setSuccessful( $this->isSuccessfulPaypalResponse( $rawResponse ) );

		if ( !empty( $rawResponse['ACK'] ) ) {
			$response->setRawStatus( $rawResponse['ACK'] );
		}

		if ( isset( $rawResponse['PROFILESTATUS'] ) ) {
			$response->setStatus( ( new RecurringPaymentsProfileStatus() )
				->normalizeStatus( $rawResponse['PROFILESTATUS'] ) )
				->setProfileId( $rawResponse['PROFILEID'] );
		} else {
			$response->setStatus( FinalStatus::FAILED );
		}

		return $response;
	}

	/**
	 * @param array $params
	 *
	 * @return ApprovePaymentResponse
	 * @throws UnexpectedValueException
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		$rawResponse = $this->api->doExpressCheckoutPayment( $params );
		$response = new ApprovePaymentResponse();
		$response->setRawResponse( $rawResponse );

		if ( !empty( $rawResponse['ACK'] ) ) {
			$response->setRawStatus( $rawResponse['ACK'] );
		}

		if ( $this->isSuccessfulPaypalResponse( $rawResponse ) ) {
			$response->setSuccessful( true );
			$response->setGatewayTxnId( $rawResponse['PAYMENTINFO_0_TRANSACTIONID'] );
			if ( !empty( $rawResponse['PAYMENTINFO_0_PAYMENTSTATUS'] ) ) {
				$response->setStatus( ( new ApprovePaymentStatus() )->normalizeStatus( $rawResponse['PAYMENTINFO_0_PAYMENTSTATUS'] ) );
			} else {
				throw new UnexpectedValueException( "Paypal API call successful but no status returned" );
			}
		} else {
			$response->setSuccessful( false );
			// when the API call fails we don't get a result in PAYMENTINFO_0_PAYMENTSTATUS so set the status here.
			$response->setStatus( FinalStatus::FAILED );
			$response->addErrors( $this->mapErrorsInResponse( $rawResponse ) );
		}

		return $response;
	}

	/**
	 * Cancel an existing PayPal subscription
	 *
	 * @param array $params Associative array with a 'subscr_id' key
	 * @return CancelSubscriptionResponse
	 */
	public function cancelSubscription( array $params ): CancelSubscriptionResponse {
		$rawResponse = $this->api->manageRecurringPaymentsProfileStatusCancel( $params );
		$response = new CancelSubscriptionResponse();
		$response->setRawResponse( $rawResponse );
		$response->setRawStatus( $rawResponse['ACK'] ?? null );

		if ( $this->isSuccessfulPaypalResponse( $rawResponse ) ) {
			$response->setSuccessful( true );

			if (
				empty( $rawResponse[ 'PROFILEID' ] ) ||
				$rawResponse[ 'PROFILEID' ] !== $params[ 'subscr_id' ]
			) {
				throw new UnexpectedValueException(
					"Paypal API call successful but incorrect or missing PROFILEID in response" );
			}

		} else {
			$response->setSuccessful( false );
			$response->setStatus( FinalStatus::FAILED );
			$response->addErrors( $this->mapErrorsInResponse( $rawResponse ) );
		}

		return $response;
	}

	/**
	 * Get the latest status from PayPal
	 *
	 * $params['gateway_session_id'] should match the PayPal EC Token
	 *
	 * @param array $params
	 * @return PaymentDetailResponse
	 */
	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse {
		$rawResponse = $this->api->getExpressCheckoutDetails( $params['gateway_session_id'] );
		return $this->mapGetDetailsResponse( $rawResponse );
	}

	/**
	 * Map Detail Response for GetExpressCheckoutDetails
	 *
	 * Not mapped, but usually present in response:
	 * CORRELATIONID (their-side request ID for debugging), COUNTRYCODE, MIDDLENAME, SUFFIX,
	 * TIMESTAMP (TODO: map to payment date), CUSTOM, INVNUM (last 2 are both order_id),
	 * BILLINGAGREEMENTACCEPTEDSTATUS, REDIRECTREQUIRED, PAYMENTREQUEST_0_AMT, PAYMENTREQUEST_0_CURRENCYCODE
	 *
	 * @param array $rawResponse
	 *
	 * @return PaymentDetailResponse
	 * @throws UnexpectedValueException
	 */
	protected function mapGetDetailsResponse( array $rawResponse ): PaymentDetailResponse {
		$response = ( new PaymentDetailResponse() )
			->setRawResponse( $rawResponse );

		if ( $this->isSuccessfulPaypalResponse( $rawResponse ) ) {
			$details = ( new DonorDetails() )
				->setEmail( $rawResponse['EMAIL'] ?? null )
				->setFirstName( $rawResponse['FIRSTNAME'] ?? null )
				->setLastName( $rawResponse['LASTNAME'] ?? null );

			$response->setSuccessful( true )
				->setDonorDetails( $details )
				->setProcessorContactID( $rawResponse['PAYERID'] ?? null );

			if ( !empty( $rawResponse['PAYMENTREQUEST_0_TRANSACTIONID'] ) ) {
				// This field is only returned after a successful transaction for DoExpressCheckout has occurred.
				$response->setGatewayTxnId( $rawResponse['PAYMENTREQUEST_0_TRANSACTIONID'] );
			}

			if ( !empty( $rawResponse['CHECKOUTSTATUS'] ) ) {
				$response->setRawStatus( $rawResponse['CHECKOUTSTATUS'] )
					->setStatus( ( new ExpressCheckoutStatus() )->normalizeStatus( $rawResponse ) );
			} else {
				throw new UnexpectedValueException( "Paypal API call successful but no status returned" );
			}
		} else {
			$response->setSuccessful( false );
			// when the API call fails we don't get a result in CHECKOUTSTATUS,
			// while if the error code is just a timeout, we do not want to make status failed, so no need to set the status here yet.
			$response->addErrors( $this->mapErrorsInResponse( $rawResponse ) );
		}

		return $response;
	}

	/**
	 * Check the API call result.
	 *
	 * 'ACK' is the Acknowledge Status for the entire transaction or API call. PayPal also returns
	 * payment-specific fields such as 'PAYMENTINFO_0_PAYMENTSTATUS', which is the payment status of the first payment.
	 * PayPal supports multiple payments in a single request. PayPal indicate this in the docs by referring to
	 * payment-specific fields with 'n' e.g. 'PAYMENTINFO_n_PAYMENTSTATUS'. This isn't relevant in WMF's case as we
	 * only ever send over one payment in a single request. PayPal used to also return 'PAYMENTINFO_0_ACK' but that's
	 * now been deprecated.
	 *
	 * @param array $rawResponse
	 *
	 * @return bool
	 */
	protected function isSuccessfulPaypalResponse( array $rawResponse ): bool {
		$paypalAcknowledgementStatus = $rawResponse['ACK'] ?? null;

		if ( $paypalAcknowledgementStatus === null ) {
			return false;
		}
		if ( $paypalAcknowledgementStatus == 'Success' ) {
			return true;
		}
		if ( $paypalAcknowledgementStatus == 'SuccessWithWarning' ) {
			Logger::warning( 'PayPal response came back with warning: ' . json_encode( $rawResponse ) );

			return true;
		}
		return false;
	}

	/**
	 * Normalize PayPal response errors
	 *
	 * TODO: We need a place where all mapping can live to avoid
	 * having it peppered across different classes. Maybe a 'ProivderResponseMapper'
	 * or something like that? It feels wrong that this class needs to know
	 * so much about Paypal's request format and tightly couples the code to
	 * the specific API method/response/version
	 *
	 * @param array $response
	 * @return array
	 */
	protected function mapErrorsInResponse( array $response ): array {
		$errors = [];
		if ( isset( $response['L_ERRORCODE0'] ) ) {
			$originalErrorCode = $response['L_ERRORCODE0'];
			$mapperErrorCode = $this->mapPaypalErrorCode( $originalErrorCode );
			$errorMessage = $response['L_LONGMESSAGE0'] ?? '';

			$errors[] = new PaymentError(
				$mapperErrorCode,
				$originalErrorCode . ": " . $errorMessage,
				LogLevel::ERROR
			);
		}
		return $errors;
	}

	/**
	 * Map Paypal error code to our own ErrorCode
	 *
	 * Documentation https://developer.paypal.com/api/nvp-soap/errors/
	 *
	 * @param string $errorCode
	 * @return int
	 */
	private function mapPaypalErrorCode( string $errorCode ): int {
		// default to unknown
		$mappedCode = ErrorCode::UNKNOWN;

		switch ( $errorCode ) {
			case '11607':
			case '10412':
				$mappedCode = ErrorCode::DUPLICATE_ORDER_ID;
				break;
			case '10486': // First attempt failed
				$mappedCode = ErrorCode::DECLINED;
				break;
			case '10411': // Timeout
				$mappedCode = ErrorCode::SERVER_TIMEOUT;
				break;
			case '81100': // Missing Parameter
				$mappedCode = ErrorCode::MISSING_REQUIRED_DATA;
				break;
			case '10410': // Invalid Paypal EC Token
				$mappedCode = ErrorCode::TRANSACTION_NOT_FOUND;
				break;
			case '10421': // Express Checkout belongs to a different customer (weird)
				$mappedCode = ErrorCode::UNEXPECTED_VALUE;
				break;
			case '11556': // Invalid subscription status for cancel action; should be active or suspended
				$mappedCode = ErrorCode::SUBSCRIPTION_CANNOT_BE_CANCELED;
				break;
		}
		return $mappedCode;
	}
}
