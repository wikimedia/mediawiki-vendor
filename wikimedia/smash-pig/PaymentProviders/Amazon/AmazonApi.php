<?php namespace SmashPig\PaymentProviders\Amazon;

use PayWithAmazon\IpnHandlerInterface;
use PayWithAmazon\PaymentsClientInterface;
use SmashPig\Core\Context;
use SmashPig\Core\SmashPigException;

/**
 * Utility functions for the PayWithAmazon SDK
 */
class AmazonApi {

	/**
	 * @var PaymentsClientInterface
	 */
	protected $client;

	/**
	 * @var AmazonApi
	 */
	protected static $instance;

	private function __construct() {
		$config = Context::get()->getProviderConfiguration();
		$this->client = $config->object( 'payments-client', true );
	}

	public static function get() {
		if ( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @param array $headers Associative array of HTTP headers
	 * @param string $body HTTP request body (should be JSON-encoded)
	 * @return IpnHandlerInterface
	 */
	public static function createIpnHandler( $headers, $body ) {
		$config = Context::get()->getProviderConfiguration();
		$retryLimit = $config->val( 'curl/retries' );
		$klass = $config->val( 'ipn-handler-class' );
		$proxy = $config->val( 'proxy' );
		$tries = 0;
		// The IPN handler constructor will make a cURL request under the hood, so we
		// retry the constructor the same number of times we have configured to retry
		// our own cURL requests.
		while ( $tries < $retryLimit - 1 ) {
			try {
				return new $klass( $headers, $body, $proxy );
			}
			catch ( \Exception $ex ) {
				$tries++;
			}
		}
		// On the last attempt, just construct with no try wrapper so the original
		// exception bubbles up with its own stack trace
		return new $klass( $headers, $body, $proxy );
	}

	/**
	 * @param string $orderReferenceId
	 * @return string Amazon's ID for the first successful capture associated
	 *  with this order reference
	 * @throws SmashPigException
	 */
	public function findCaptureId( $orderReferenceId ) {
		// The order reference details should contain an IdList with all of the
		// authorizations that have been made against the order reference.  We
		// should only ever have one authorization per order reference.
		$details = $this->getOrderReferenceDetails( $orderReferenceId );

		if ( !isset( $details['IdList'] ) || !isset( $details['IdList']['member'] ) ) {
			throw new SmashPigException(
				"No authorizations found for order reference $orderReferenceId!"
			);
		}
		$authorizationIds = (array)$details['IdList']['member'];
		// Check the status of each authorization against the order reference
		foreach ( $authorizationIds as $id ) {
			$authResult = $this->client->getAuthorizationDetails( [
				'amazon_authorization_id' => $id,
			] )->toArray();
			if ( !empty( $authResult['Error'] ) ) {
				throw new SmashPigException( $authResult['Error']['Message'] );
			}
			$details = $authResult['GetAuthorizationDetailsResult']['AuthorizationDetails'];
			$state = $details['AuthorizationStatus']['State'];
			// Once we successfully capture payment against an authorization, it
			// transitions to the 'Closed' state. Failed attempts are 'Declined'
			if ( $state === 'Closed' ) {
				// And guess what?  The authorization ID is exactly the same as the
				// capture ID (which we store as the gateway txn id), with one letter
				// changed.
				$captureId = substr( $id, 0, 20 ) . 'C' . substr( $id, 21 );
				return $captureId;
			}
		}
		throw new SmashPigException(
			"No successful authorizations found for order reference $orderReferenceId!"
		);
	}

	/**
	 * @param string $orderReferenceId
	 * @return string|null Merchant reference for the order ID, or null if
	 *  not set
	 */
	public function findMerchantReference( $orderReferenceId ) {
		$details = $this->getOrderReferenceDetails( $orderReferenceId );

		if ( isset( $details['SellerOrderAttributes']['SellerOrderId'] ) ) {
			return $details['SellerOrderAttributes']['SellerOrderId'];
		}
		return null;
	}

	/**
	 * @param string $orderReferenceId 19 character Amazon order ID
	 * @return array OrderReferenceDetails as an associative array
	 *  @see https://payments.amazon.com/documentation/apireference/201752660
	 * @throws SmashPigException
	 */
	public function getOrderReferenceDetails( $orderReferenceId ) {
		$getDetailsResult = $this->client->getOrderReferenceDetails(
			[
				'amazon_order_reference_id' => $orderReferenceId,
			]
		)->toArray();
		if ( !empty( $getDetailsResult['Error'] ) ) {
			throw new SmashPigException( $getDetailsResult['Error']['Message'] );
		}
		return $getDetailsResult['GetOrderReferenceDetailsResult']['OrderReferenceDetails'];
	}

	public function authorizeAndCapture( $orderReferenceId ) {
		$details = $this->getOrderReferenceDetails( $orderReferenceId );
		$state = $details['OrderReferenceStatus']['State'];
		if ( $state !== 'Open' ) {
			throw new SmashPigException(
				"Cannot capture order in state $state."
			);
		}
		$amount = $details['OrderTotal']['Amount'];
		$currency = $details['OrderTotal']['CurrencyCode'];
		$merchantReference = $details['SellerOrderAttributes']['SellerOrderId'];

		$authorizeResult = $this->client->authorize(
			[
				'amazon_order_reference_id' => $orderReferenceId,
				'authorization_amount' => $amount,
				'currency_code' => $currency,
				'capture_now' => true, // combine authorize and capture steps
				'authorization_reference_id' => $merchantReference,
				'transaction_timeout' => 0,
			]
		)->toArray();
		if ( !empty( $authorizeResult['Error'] ) ) {
			throw new SmashPigException( $authorizeResult['Error']['Message'] );
		}
		return $authorizeResult['AuthorizeResult']['AuthorizationDetails'];
	}

	public function cancelOrderReference( $orderReferenceId, $reason = null ) {
		$params = [
			'amazon_order_reference_id' => $orderReferenceId
		];
		if ( $reason ) {
			$params['cancelation_reason'] = $reason;
		}
		$result = $this->client->cancelOrderReference( $params )->toArray();
		if ( !empty( $result['Error'] ) ) {
			throw new SmashPigException( $result['Error']['Message'] );
		}
	}

	public static function isAmazonGeneratedMerchantReference( $reference ) {
		return substr( $reference, 0, 10 ) === 'AUTHORIZE_';
	}
}
