<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

class PaymentProvider implements IPaymentProvider {

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
	 * @inheritDoc
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}

	public function getLatestPaymentStatus( array $params ): PaymentDetailResponse {
		$rawResponse = $this->api->makeApiCall( [
			'METHOD' => 'GetExpressCheckoutDetails',
			'TOKEN' => $params['gateway_session_id']
		] );

		return $this->mapGetDetailsResponse( $rawResponse );
	}

	protected function mapGetDetailsResponse( array $rawResponse ) {
		$details = ( new DonorDetails() )
			->setEmail( $rawResponse['EMAIL'] ?? null )
			->setFirstName( $rawResponse['FIRSTNAME'] ?? null )
			->setLastName( $rawResponse['LASTNAME'] ?? null );

		$rawStatus = $rawResponse['CHECKOUTSTATUS'];

		$response = ( new PaymentDetailResponse() )
			->setRawResponse( $rawResponse )
			->setSuccessful( $this->checkPaypalAcknowledgmentStatus( $rawResponse ) )
			->setDonorDetails( $details )
			->setRawStatus( $rawStatus )
			->setProcessorContactID( $rawResponse['PAYERID'] ?? null )
			->setStatus( ( new ExpressCheckoutStatus() )->normalizeStatus( $rawStatus ) )
			->setGatewayTxnId( $rawResponse['PAYMENTINFO_0_TRANSACTIONID'] ?? '' );
		// Not mapped, but usually present in response:
		// CORRELATIONID (their-side request ID for debugging), COUNTRYCODE, MIDDLENAME, SUFFIX,
		// TIMESTAMP (TODO: map to payment date), CUSTOM, INVNUM (last 2 are both order_id),
		// BILLINGAGREEMENTACCEPTEDSTATUS, REDIRECTREQUIRED, PAYMENTREQUEST_0_AMT, PAYMENTREQUEST_0_CURRENCYCODE

		return $response;
	}

	protected function checkPaypalAcknowledgmentStatus( array $rawResponse ): bool {
		if ( empty( $rawResponse['ACK'] ) ) {
			return false;
		}
		if ( $rawResponse['ACK'] == 'Success' ) {
			return true;
		}
		if ( $rawResponse['ACK'] == 'SuccessWithWarning' ) {
			Logger::warning( 'PayPal response came back with warning: ' . json_encode( $rawResponse ) );
			return true;
		}
		return false;
	}
}
