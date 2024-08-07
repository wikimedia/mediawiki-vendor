<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\Core\RetryableException;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * Make sure a message that arrives before the pending databases entry gets
 * written is properly requeued for retry.
 *
 * @group Adyen
 */
class RequeueMessageTest extends BaseAdyenTestCase {

	public function testRequeueMessage() {
		$this->expectException( RetryableException::class );
		$auth = JsonSerializableObject::fromJsonProxy(
			'SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation',
			file_get_contents( __DIR__ . '/../Data/auth.json' )
		);

		$payload = ProcessCaptureRequestJob::factory( $auth );
		$job = new ProcessCaptureRequestJob();
		$job->payload = $payload['payload'];
		$job->execute();
	}

}
