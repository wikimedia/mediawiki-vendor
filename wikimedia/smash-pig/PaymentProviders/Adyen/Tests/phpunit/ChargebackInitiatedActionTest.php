<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\Actions\ChargebackInitiatedAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Chargeback;

/**
 * @group Adyen
 */
class ChargebackInitiatedActionTest extends BaseAdyenTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $refundQueue;

	public function setUp() : void {
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
		$chargeback->pspReference = "T89RVDS9V379R782";
		$chargeback->merchantReference = "testMerchantRef1";
		$chargeback->reason = "test";

		$action = new ChargebackInitiatedAction();
		$action->execute( $chargeback );
		$refund = $this->refundQueue->pop();
		$this->assertEquals( $chargeback->pspReference, $refund['gateway_parent_id'] );
	}

	public function testFailedChargeback() {
		$chargeback = new Chargeback();
		$chargeback->success = false;

		$action = new ChargebackInitiatedAction();
		$action->execute( $chargeback );

		$refund = $this->refundQueue->pop();

		$this->assertNull( $refund, 'Should not have queued a chargeback' );
	}

}
