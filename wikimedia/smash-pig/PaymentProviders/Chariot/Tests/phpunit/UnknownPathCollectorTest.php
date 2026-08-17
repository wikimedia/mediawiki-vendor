<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use SmashPig\PaymentProviders\Chariot\UnknownPathCollector;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Chariot
 */
class UnknownPathCollectorTest extends BaseSmashPigUnitTestCase {

	public function testReturnsNoUnknownsWhenAllPathsAreKnown(): void {
		$collector = new UnknownPathCollector();

		$collector->scanDeposit(
			[
				'id' => 'deposit_123',
				'transfer' => [
					'amount' => 100,
				],
			],
			[
				'id',
				'transfer',
				'transfer.amount',
			]
		);

		$this->assertSame( [], $collector->getUnknowns() );
	}

	public function testCollectsUnknownScalarPath(): void {
		$collector = new UnknownPathCollector();

		$collector->scanDeposit(
			[
				'id' => 'deposit_123',
				'new_field' => 'surprise',
			],
			[
				'id',
			]
		);

		$this->assertSame(
			[
				'new_field' => [
					'path' => 'new_field',
					'count' => 1,
					'sample' => 'surprise',
				],
			],
			$collector->getUnknowns()
		);
	}

	public function testCollectsUnknownNestedPath(): void {
		$collector = new UnknownPathCollector();

		$collector->scanDeposit(
			[
				'transfer' => [
					'amount' => 100,
					'new_nested_field' => 'abc',
				],
			],
			[
				'transfer',
				'transfer.amount',
			]
		);

		$this->assertSame(
			[
				'transfer.new_nested_field' => [
					'path' => 'transfer.new_nested_field',
					'count' => 1,
					'sample' => 'abc',
				],
			],
			$collector->getUnknowns()
		);
	}

	public function testCountsRepeatedUnknownPath(): void {
		$collector = new UnknownPathCollector();

		$collector->scanDeposit(
			[
				'new_field' => 'first',
			],
			[]
		);

		$collector->scanDeposit(
			[
				'new_field' => 'second',
			],
			[]
		);

		$this->assertSame(
			[
				'new_field' => [
					'path' => 'new_field',
					'count' => 2,
					'sample' => 'first',
				],
			],
			$collector->getUnknowns()
		);
	}

	public function testCollectsUnknownListPath(): void {
		$collector = new UnknownPathCollector();

		$collector->scanDonation(
			[
				'artifacts' => [
					[
						'id' => 'artifact_1',
						'type' => 'receipt',
					],
				],
			],
			[
				'artifacts',
			]
		);

		$this->assertSame(
			[
				'artifacts[].id' => [
					'path' => 'artifacts[].id',
					'count' => 1,
					'sample' => 'artifact_1',
				],
				'artifacts[].type' => [
					'path' => 'artifacts[].type',
					'count' => 1,
					'sample' => 'receipt',
				],
			],
			$collector->getUnknowns()
		);
	}

	public function testKnownListChildrenAreNotUnknown(): void {
		$collector = new UnknownPathCollector();

		$collector->scanDonation(
			[
				'artifacts' => [
					[
						'id' => 'artifact_1',
						'type' => 'receipt',
					],
				],
			],
			[
				'artifacts',
				'artifacts[].id',
				'artifacts[].type',
			]
		);

		$this->assertSame( [], $collector->getUnknowns() );
	}

	public function testSamplesBooleanAndNullValuesWithoutStringCasting(): void {
		$collector = new UnknownPathCollector();

		$collector->scanDeposit(
			[
				'is_active' => true,
				'missing_value' => null,
			],
			[]
		);

		$this->assertSame(
			[
				'is_active' => [
					'path' => 'is_active',
					'count' => 1,
					'sample' => true,
				],
				'missing_value' => [
					'path' => 'missing_value',
					'count' => 1,
					'sample' => null,
				],
			],
			$collector->getUnknowns()
		);
	}

}
