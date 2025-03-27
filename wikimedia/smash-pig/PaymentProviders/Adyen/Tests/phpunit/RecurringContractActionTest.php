<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\Actions\RecurringContractAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RecurringContract;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * Verify Adyen RecurringContractAction functions
 *
 * @group Adyen
 */
class RecurringContractActionTest extends BaseAdyenTestCase {

	public function setUp(): void {
		parent::setUp();
		$globalConfig = Context::get()->getGlobalConfiguration();
		$this->jobQueue = $globalConfig->object( 'data-store/jobs-adyen' );
	}

	public function testGr4vyInitiatedRecurringContract() {
		$recurring = new RecurringContract();
		$recurring->success = true;

		$recurring->additionalData['metadata.gr4vy_intent'] = 'authorize';
		$action = new RecurringContractAction();
		$action->execute( $recurring );
		$job = $this->jobQueue->pop();
		$this->assertNull( $job, 'Should not have queued a refund' );
	}
}
