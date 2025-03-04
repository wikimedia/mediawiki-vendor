<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\RetryableException;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
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
		$auth = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/auth.json' ), true )
		);

		$payload = ProcessCaptureRequestJob::factory( $auth );
		$job = new ProcessCaptureRequestJob();
		$job->payload = $payload['payload'];
		$job->execute();
	}

}
