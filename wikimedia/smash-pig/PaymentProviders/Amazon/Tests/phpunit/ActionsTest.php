<?php
namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\PaymentProviders\Amazon\Actions\ReconstructMerchantReference;
use SmashPig\PaymentProviders\Amazon\Actions\RetryAuthorization;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\AuthorizationDeclined;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;

/**
 * @group Amazon
 */
class ActionsTest extends AmazonTestCase {

	public function testReconstructMerchantId() {
		$captureCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureCompleted.json" );
		$captureCompleted["CaptureDetails"]["CaptureReferenceId"] = 'AUTHORIZE_123456767';
		$message = new CaptureCompleted( $captureCompleted );
		$this->assertEquals( 'AUTHORIZE_123456767', $message->getOrderId() );
		$action = new ReconstructMerchantReference();
		$action->execute( $message );
		// This ID comes from getOrderReferenceDetails.json
		$this->assertEquals( '123456789-0', $message->getOrderId() );
	}

	/**
	 * Don't waste API calls when it's not an AUTHORIZE_ id
	 */
	public function testReconstructMerchantIdNotNeeded() {
		$captureCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureCompleted.json" );
		$message = new CaptureCompleted( $captureCompleted );
		$action = new ReconstructMerchantReference();
		$action->execute( $message );
		$this->assertEquals( '98765432-1', $message->getOrderId() );
		$this->assertCount( 0, $this->mockClient->calls );
	}

	/**
	 * Retry auths declined because TransactionTimedOut
	 */
	public function testRetryAuthorizationTimedOut() {
		$authDeclined = $this->loadJson( __DIR__ . "/../Data/IPN/AuthorizationDeclined.json" );
		$message = new AuthorizationDeclined( $authDeclined );
		$action = new RetryAuthorization();
		$action->execute( $message );
		$this->assertArrayHasKey( 'authorize', $this->mockClient->calls );
		$params = $this->mockClient->calls['authorize'][0];
		$originalDetails = $authDeclined['AuthorizationDetails'];
		$this->assertEquals(
			$originalDetails['AuthorizationAmount']['Amount'],
			$params['authorization_amount']
		);
		$this->assertEquals(
			$originalDetails['AuthorizationReferenceId'],
			$params['authorization_reference_id']
		);
		$this->assertEquals(
			$message->getOrderReferenceId(),
			$params['amazon_order_reference_id']
		);
	}
}
