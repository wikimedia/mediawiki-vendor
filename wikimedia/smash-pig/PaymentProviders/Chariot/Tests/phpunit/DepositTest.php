<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Chariot\Deposit;

class DepositTest extends TestCase {

	public function testGetBasicFields(): void {
		$deposit = new Deposit( [
			'id' => 'deposit_01kt1vy4',
			'created_at' => '2026-06-01T15:13:17.627991Z',
			'updated_at' => '2026-06-01T16:13:17Z',
			'settled_at' => '2026-06-02T12:30:35Z',
			'payment_source_id' => 'payment_source_7yec8861',
			'transfer' => [
				'amount' => 124605,
				'currency' => 'USD',
				'inbound_ach_transfer' => [
					'originator_company_name' => 'My FOUNDATION',
				],
			],
		] );

		$this->assertSame( 'deposit_01kt1vy4', $deposit->getId() );
		$this->assertSame( '2026-06-01T15:13:17.627991Z', $deposit->getCreatedAt() );
		$this->assertSame( '2026-06-02T12:30:35Z', $deposit->getSettledAt() );
		$this->assertSame( 'payment_source_7yec8861', $deposit->getPaymentSourceId() );
	}

}
