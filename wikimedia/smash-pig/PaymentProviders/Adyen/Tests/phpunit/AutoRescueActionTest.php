<?php

namespace SmashPig\PaymentProviders\Adyen\Test;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Autorescue;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

class AutoRescueActionTest extends BaseAdyenTestCase {
	private FifoQueueStore $jobsAdyenQueue;
	private FifoQueueStore $recurringQueue;

	public function setUp(): void {
		parent::setUp();
		$this->jobsAdyenQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-adyen' );
		$this->recurringQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/recurring' );
	}

	public function testAutoRescueMessageIsInstanceOfAuthorisation(): void {
		$authorisation = Autorescue::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' ), true )
		);

		$this->assertInstanceOf( Authorisation::class, $authorisation );
	}

	public function testAutoRescueIsRecurringInstallmentReturnsCorrectValue(): void {
		$authorisation = Autorescue::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' ), true )
		);
		$this->assertTrue( $authorisation->isSuccessfulAutoRescue() );
	}

	public function testSuccessfulAutoRescueMessageTransferredToJobsAdyenQueue(): void {
		$authorisation = Autorescue::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' ), true )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$this->assertEquals( $msg['payload']['retryRescueReference'], $authorisation->retryRescueReference );
		$this->assertEquals( $msg['payload']['pspReference'], $authorisation->pspReference );
		$this->assertEquals( "SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob", $msg['class'] );
	}

	public function testSuccessfulAutoRescueAuthorisationMessageCapture(): void {
		$authorisation = Autorescue::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' ), true )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$capture = new $msg['class']();
		$capture->payload = $msg['payload'];
		$approvePaymentResult = AdyenTestConfiguration::getSuccessfulApproveResult();
		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => 10,
				'currency' => 'USD',
				'gateway_txn_id' => $authorisation->pspReference
			] )
			->willReturn( $approvePaymentResult );

		$capture->execute();
		$this->assertEquals( $msg['payload']['merchantReference'], $authorisation->merchantReference );
		$this->assertEquals( $msg['payload']['shopperReference'], $authorisation->shopperReference );

		$recurringMsg = $this->recurringQueue->pop();
		$this->assertNotNull( $recurringMsg );
		$this->assertEquals( $recurringMsg['rescue_reference'], $authorisation->retryRescueReference );
		$this->assertEquals( $recurringMsg['gateway_txn_id'], $authorisation->pspReference );
		$this->assertEquals( 'subscr_payment', $recurringMsg['txn_type'] );
		$this->assertTrue( $recurringMsg['is_successful_autorescue'] );
	}

	public function testSuccessfulAutoRescueAuthorisationMessageCaptureJPY(): void {
		$authorisation = Autorescue::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth_jpy.json' ), true )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$capture = new $msg['class']();
		$capture->payload = $msg['payload'];
		$approvePaymentResult = AdyenTestConfiguration::getSuccessfulApproveResult();
		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => 335,
				'currency' => 'JPY',
				'gateway_txn_id' => $authorisation->pspReference
			] )
			->willReturn( $approvePaymentResult );

		$capture->execute();
		$this->assertEquals( $msg['payload']['merchantReference'], $authorisation->merchantReference );
		$this->assertEquals( $msg['payload']['shopperReference'], $authorisation->shopperReference );

		$recurringMsg = $this->recurringQueue->pop();
		$this->assertNotNull( $recurringMsg );
		$this->assertEquals( $recurringMsg['rescue_reference'], $authorisation->retryRescueReference );
		$this->assertEquals( $recurringMsg['gateway_txn_id'], $authorisation->pspReference );
		$this->assertEquals( 'subscr_payment', $recurringMsg['txn_type'] );
		$this->assertTrue( $recurringMsg['is_successful_autorescue'] );
	}

	public function testNoCaptureForAutoRescueMessage(): void {
		$authorisation = Autorescue::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/successful_auto_rescue_auth.json' ), true )
		);
		$action = new PaymentCaptureAction();
		$action->execute( $authorisation );

		$msg = $this->jobsAdyenQueue->pop();

		$capture = new $msg['class']();
		$capture->payload = $msg['payload'];

		$capture->execute();

		$recurringMsg = $this->recurringQueue->pop();
		$this->assertNull( $recurringMsg );
	}

	/**
	 * We should send a subscr_cancel message when we get a failed autorescue IPN
	 */
	public function testEndedAutoRescue(): void {
		$autorescue = Autorescue::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Autorescue_failed.json' ), true )
		);
		/** @var Autorescue $autorescue $action */
		$autorescue->runActionChain();

		$jobMsg = $this->jobsAdyenQueue->pop();
		$this->assertNull( $jobMsg );
		$recurMsg = QueueWrapper::getQueue( 'recurring' )->pop();
		$this->assertNotNull( $recurMsg );

		SourceFields::removeFromMessage( $recurMsg );
		$this->assertEquals( [
			'txn_type' => 'subscr_cancel',
			'gateway' => 'adyen',
			'rescue_reference' => $autorescue->retryRescueReference,
			'is_autorescue' => true,
			'cancel_reason' => 'Payment cannot be rescued: maximum failures reached'
		], $recurMsg );
	}

	/**
	 * Don't send an extra subscr_cancel message on the failed auth
	 */
	public function testEndedAutoRescueAuth(): void {
		$authorisation = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/ended_auto_rescue_auth.json' ), true )
		);
		/** @var Authorisation $authorisation $action */
		$authorisation->runActionChain();

		$jobMsg = $this->jobsAdyenQueue->pop();
		$this->assertNull( $jobMsg );
		$recurMsg = QueueWrapper::getQueue( 'recurring' )->pop();
		$this->assertNull( $recurMsg );
	}
}
