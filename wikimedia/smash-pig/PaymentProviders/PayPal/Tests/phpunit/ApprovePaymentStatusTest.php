<?php

namespace SmashPig\PaymentProviders\PayPal\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\PayPal\ApprovePaymentStatus;
use UnexpectedValueException;

/**
 * @group PayPal
 */
class ApprovePaymentStatusTest extends TestCase {
	protected $expectedMappedStatuses = [
		'None' => FinalStatus::UNKNOWN,
		'Processed' => FinalStatus::COMPLETE,
		'Completed' => FinalStatus::COMPLETE,
		'Canceled-Reversal' => FinalStatus::COMPLETE,
		'Denied' => FinalStatus::FAILED,
		'Failed' => FinalStatus::FAILED,
		'Voided' => FinalStatus::FAILED,
		'Expired' => FinalStatus::TIMEOUT,
		'In-Progress' => FinalStatus::PENDING,
		'Partially-Refunded' => FinalStatus::REFUNDED,
		'Refunded' => FinalStatus::REFUNDED,
		'Reversed' => FinalStatus::REVERSED,
		'Completed_Funds_Held' => FinalStatus::ON_HOLD,
	];

	/**
	 * @dataProvider getPaypalStatuses
	 */
	public function testNormalizeStatus( $status ) {
		$statusNormalizer = new ApprovePaymentStatus();
		$this->assertEquals( $this->expectedMappedStatuses[$status], $statusNormalizer->normalizeStatus( $status ) );
	}

	public function testInvalidStatusThrowsException() {
		$this->expectException( UnexpectedValueException::class );
		$invalidStatus = 'not_a_real_status';
		$statusNormalizer = new ApprovePaymentStatus();
		$statusNormalizer->normalizeStatus( $invalidStatus );
	}

	public function getPaypalStatuses() {
		return [
			[ 'None' ],
			[ 'Processed' ],
			[ 'Completed' ],
			[ 'Canceled-Reversal' ],
			[ 'Denied' ],
			[ 'Failed' ],
			[ 'Voided' ],
			[ 'Expired' ],
			[ 'In-Progress' ],
			[ 'Partially-Refunded' ],
			[ 'Refunded' ],
			[ 'Reversed' ],
			[ 'Completed_Funds_Held' ]
		];
	}
}
