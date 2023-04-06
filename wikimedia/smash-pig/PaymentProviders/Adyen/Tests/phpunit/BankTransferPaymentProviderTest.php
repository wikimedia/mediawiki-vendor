<?php
namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Adyen\BankTransferPaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 */
class BankTransferPaymentProviderTest extends BaseAdyenTestCase {

	/**
	 * @var BankTransferPaymentProvider
	 */
	public $provider;

	public function setUp(): void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/rtbt' );
	}

	public function testGetHostedPaymentDetailsCancelled() {
		$redirectResult = 'askjdfhakjsdfhaksdjfhaksdjfh';
		$this->mockApi->expects( $this->once() )
			->method( 'getPaymentDetails' )
			->with( $redirectResult )
			->willReturn( json_decode(
				file_get_contents( __DIR__ . '/../Data/getHostedPaymentDetails_Cancelled.json' ), true
			) );
		$result = $this->provider->getHostedPaymentDetails( $redirectResult );
		$this->assertEquals( FinalStatus::CANCELLED, $result->getStatus() );
	}
}
