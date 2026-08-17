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

	public function testGetCheckNumber(): void {
		$deposit = new Deposit( [
			'transfer' => [
				'check_deposit' => [
					'auxiliary_on_us' => '123456',
				],
			],
		] );

		$this->assertSame( '123456', $deposit->getCheckNumber() );
	}

	public function testGetCheckNumberReturnsEmptyStringWhenMissing(): void {
		$deposit = new Deposit( [
			'transfer' => [],
		] );

		$this->assertSame( '', $deposit->getCheckNumber() );
	}

	public function testGetSettledAmountInMinorUnits(): void {
		$deposit = new Deposit( [
			'transfer' => [
				'amount' => 124605,
				'currency' => 'USD',
			],
		] );

		$this->assertSame( 124605, $deposit->getSettledAmountInMinorUnits() );
	}

	public function testGetSettledAmountInMinorUnitsReturnsZeroWhenMissing(): void {
		$deposit = new Deposit( [] );

		$this->assertSame( 0, $deposit->getSettledAmountInMinorUnits() );
	}

	public function testGetSettledAmountRounded(): void {
		$deposit = new Deposit( [
			'transfer' => [
				'amount' => 124605,
				'currency' => 'USD',
			],
		] );

		$this->assertSame( '1246.05', $deposit->getSettledAmount() );
	}

	public function testGetZeroAmountRounded(): void {
		$deposit = new Deposit( [
			'transfer' => [
				'currency' => 'USD',
			],
		] );

		$this->assertSame( '0.00', $deposit->getZeroAmountRounded() );
	}

}
