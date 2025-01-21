<?php
namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\PaymentProviders\Amazon\AmazonApi;

/**
 * @group Amazon
 */
class ApiTest extends AmazonTestCase {

	public function testFindParent() {
		$this->mockClient->returns['getAuthorizationDetails'][] = 'Declined';
		$this->mockClient->returns['getAuthorizationDetails'][] = 'Closed';
		$parentId = AmazonApi::get()->findCaptureId( 'P01-0133129-0199515' );
		$this->assertEquals( 'P01-0133129-0199515-C019658', $parentId, 'Did not get the right refund parent ID' );
	}
}
