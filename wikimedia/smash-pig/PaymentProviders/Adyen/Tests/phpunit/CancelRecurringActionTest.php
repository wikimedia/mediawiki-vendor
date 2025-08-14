<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Adyen\Actions\CancelRecurringAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * Cancel auto-rescue Recurring functions
 *
 * @group Adyen
 */
class CancelRecurringActionTest extends BaseAdyenTestCase {

	public function testGr4vyInitiatedAutoRescue() {
		$recurring = new Authorisation();
		$recurring->success = true;
		$recurring->additionalData['metadata.gr4vy_intent'] = 'authorize';
		$action = new CancelRecurringAction();
		$action->execute( $recurring );
		$job = QueueWrapper::getQueue( 'recurring' )->pop();
		$this->assertNull( $job, 'Should not have queued for auto rescue cancel' );
	}
}
