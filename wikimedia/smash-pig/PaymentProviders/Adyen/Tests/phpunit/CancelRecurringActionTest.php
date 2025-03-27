<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Adyen\Actions\CancelRecurringAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * Cancel auto-rescue Recurring functions
 *
 * @group Adyen
 */
class CancelRecurringActionTest extends BaseAdyenTestCase {

	protected $pendingMessage;

	public function setUp(): void {
		parent::setUp();
		$globalConfig = Context::get()->getGlobalConfiguration();
		$this->jobQueue = $globalConfig->object( 'data-store/recurring' );
	}

	public function testGr4vyInitiatedAutoRescue() {
		$recurring = new Authorisation();
		$recurring->success = true;
		$recurring->additionalData['metadata.gr4vy_intent'] = 'authorize';
		$action = new CancelRecurringAction();
		$action->execute( $recurring );
		$job = $this->jobQueue->pop();
		$this->assertNull( $job, 'Should not have queued for auto rescue cancel' );
	}
}
