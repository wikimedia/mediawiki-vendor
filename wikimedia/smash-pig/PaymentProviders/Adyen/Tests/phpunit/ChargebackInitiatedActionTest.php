<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\Actions\ChargebackInitiatedAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Chargeback;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\SecondChargeback;

/**
 * @group Adyen
 */
class ChargebackInitiatedActionTest extends BaseAdyenTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $refundQueue;

	public function setUp(): void {
		parent::setUp();
		$this->refundQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/refund' );
	}

	public function testSuccessfulChargeback() {
		$chargeback = new Chargeback();
		$chargeback->merchantAccountCode = 'WikimediaTest';
		$chargeback->currency = 'USD';
		$chargeback->amount = 10.00;
		$chargeback->eventDate = "";
		$chargeback->success = true;
		$chargeback->parentPspReference = "T89RVDS9V379R782";
		$chargeback->pspReference = "DAS676ASD5ASD77";
		$chargeback->merchantReference = "testMerchantRef1";
		$chargeback->paymentMethod = "jcb";
		$chargeback->reason = "test";

		$action = new ChargebackInitiatedAction();
		$action->execute( $chargeback );
		$refund = $this->refundQueue->pop();
		$this->assertEquals( $chargeback->parentPspReference, $refund['gateway_parent_id'] );
		$this->assertEquals( $chargeback->paymentMethod, $refund['payment_method'] );
		$this->assertEquals( $chargeback->merchantReference, $refund['order_id'] );
		$this->assertEquals( $chargeback->pspReference, $refund['gateway_refund_id'] );
		$this->assertEquals( 'chargeback', $refund['type'] );
	}

	public function testFailedChargeback() {
		$chargeback = new Chargeback();
		$chargeback->success = false;

		$action = new ChargebackInitiatedAction();
		$action->execute( $chargeback );

		$refund = $this->refundQueue->pop();

		$this->assertNull( $refund, 'Should not have queued a chargeback' );
	}

	public function testSuccessfulSecondChargeBack() {
		$chargeback = new SecondChargeback();
		$chargeback->merchantAccountCode = 'WikimediaTest';
		$chargeback->currency = 'USD';
		$chargeback->amount = 10.00;
		$chargeback->eventDate = "";
		$chargeback->success = true;
		$chargeback->pspReference = "DAS676ASD5ASD77";
		$chargeback->paymentMethod = "amex";
		$chargeback->merchantReference = "testMerchantRef1";
		$chargeback->reason = "test second chargeback";

		$action = new ChargebackInitiatedAction();
		$action->execute( $chargeback );
		$refund = $this->refundQueue->pop();

		$this->assertEquals( $chargeback->pspReference, $refund['gateway_refund_id'] );
		$this->assertEquals( $chargeback->paymentMethod, $refund['payment_method'] );
		$this->assertEquals( $chargeback->merchantReference, $refund['order_id'] );
		// pspReference should have also been mapped to gateway_parent_id
		$this->assertEquals( $chargeback->pspReference, $refund['gateway_parent_id'] );
	}

	public function testGr4vyInitiatedChargeback() {
		$chargeback = new Chargeback();
		$chargeback->success = true;
		$chargeback->additionalData['metadata.gr4vy_intent'] = 'authorize';
		$action = new ChargebackInitiatedAction();
		$action->execute( $chargeback );
		$refund = $this->refundQueue->pop();
		$this->assertNull( $refund, 'Should not have queued a chargeback' );
	}
}
