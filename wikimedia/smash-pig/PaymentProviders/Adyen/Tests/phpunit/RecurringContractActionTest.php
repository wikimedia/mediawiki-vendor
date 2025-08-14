<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Adyen\Actions\RecurringContractAction;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RecurringContract;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * Verify Adyen RecurringContractAction functions
 *
 * @group Adyen
 */
class RecurringContractActionTest extends BaseAdyenTestCase {

	public function testGr4vyInitiatedRecurringContract() {
		$recurring = new RecurringContract();
		$recurring->success = true;

		$recurring->additionalData['metadata.gr4vy_intent'] = 'authorize';
		$action = new RecurringContractAction();
		$action->execute( $recurring );
		$job = QueueWrapper::getQueue( 'jobs-adyen' )->pop();
		$this->assertNull( $job, 'Should not have queued a refund' );
	}
}
