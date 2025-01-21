<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\Actions\RefundInitiatedAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Refund;

/**
 * @group Adyen
 */
class RefundInitiatedActionTest extends BaseAdyenTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $refundQueue;

	public function setUp() : void {
		parent::setUp();
		$this->refundQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/refund' );
	}

	public function testSuccessfulRefund() {
		$refund = new Refund();
		$refund->merchantAccountCode = 'WikimediaTest';
		$refund->currency = 'USD';
		$refund->amount = 10.00;
		$refund->paymentMethod = 'jcb';
		$refund->eventDate = "2023-12-28T12:21:16+01:00";
		$refund->success = true;
		$refund->parentPspReference = "T89RVDS9V379R782";
		$refund->pspReference = "DAS676ASD5ASD77";
		$refund->merchantReference = "testMerchantRef1";
		$refund->reason = "test";

		$action = new RefundInitiatedAction();
		$action->execute( $refund );
		$queueMessage = $this->refundQueue->pop();
		$this->assertEquals( $refund->paymentMethod, $queueMessage['payment_method'] );
		$this->assertEquals( $refund->parentPspReference, $queueMessage['gateway_parent_id'] );
		$this->assertEquals( $refund->merchantReference, $queueMessage['order_id'] );
		$this->assertEquals( $refund->pspReference, $queueMessage['gateway_refund_id'] );
		$this->assertEquals( 'refund', $queueMessage['type'] );
	}

	public function testFailedRefund() {
		$refund = new Refund();
		$refund->success = false;

		$action = new RefundInitiatedAction();
		$action->execute( $refund );

		$queueMessage = $this->refundQueue->pop();

		$this->assertNull( $queueMessage, 'Should not have queued a chargeback' );
	}

	public function testGr4vyInitiatedRefund() {
		$refund = new Refund();
		$refund->success = true;

		$refund->additionalData['metadata.gr4vy_intent'] = 'authorize';
		$action = new RefundInitiatedAction();
		$action->execute( $refund );

		$job = $this->refundQueue->pop();
		$this->assertNull( $job, 'Should not have queued a refund' );
	}
}
