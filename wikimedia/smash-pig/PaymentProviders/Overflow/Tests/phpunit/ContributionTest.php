<?php

namespace SmashPig\PaymentProviders\Overflow\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Overflow\Contribution;

class ContributionTest extends TestCase {

	/**
	 * @dataProvider provideContributions
	 */
	public function testContribution( array $data ): void {
		$object = new Contribution(
			$data['contribution'],
			$data['lineItem']
		);

		$this->assertNotEmpty( $object->getId() );
		$this->assertNotEmpty( $object->getType() );
		$this->assertSame( 'PAID_OUT', $object->getStatus() );
		$this->assertGreaterThan( 0, $object->getAmountInMinorUnits() );
		$this->assertNotEmpty( $object->getContributionDate() );
	}

	/**
	 * @dataProvider provideContributions
	 */
	public function testLineItem( array $data ): void {
		$object = new Contribution(
			$data['contribution'],
			$data['lineItem']
		);

		$this->assertSame(
			$data['lineItem']['referenceId'],
			$object->getLineItemReferenceId()
		);

		$this->assertSame(
			$data['lineItem']['grossValueInCents'],
			$object->getLineItemGrossAmountInMinorUnits()
		);
	}

	/**
	 * @dataProvider provideContributions
	 */
	public function testDonor( array $data ): void {
		$object = new Contribution(
			$data['contribution'],
			$data['lineItem']
		);

		$donor = $data['contribution']['donor'] ?? [];

		$this->assertSame(
			$donor['firstName'] ?? '',
			$object->getFirstName()
		);

		$this->assertSame(
			$donor['lastName'] ?? '',
			$object->getLastName()
		);

		$this->assertSame(
			$donor['email'] ?? '',
			$object->getEmail()
		);
	}

	/**
	 * @dataProvider provideContributions
	 */
	public function testAddressCanBeAbsent( array $data ): void {
		$object = new Contribution(
			$data['contribution'],
			$data['lineItem']
		);

		$address = $data['contribution']['donor']['address'] ?? [];

		$this->assertSame(
			$address['city'] ?? '',
			$object->getCity()
		);

		$this->assertSame(
			$address['state'] ?? '',
			$object->getStateProvince()
		);

		$this->assertSame(
			$address['zip'] ?? '',
			$object->getPostalCode()
		);
	}

	/**
	 * @dataProvider provideContributions
	 */
	public function testPaymentMethod( array $data ): void {
		$object = new Contribution(
			$data['contribution'],
			$data['lineItem']
		);

		$paymentMethod = $data['contribution']['paymentMethod'] ?? [];

		$this->assertSame(
			$paymentMethod['type'] ?? '',
			$object->getPaymentMethodType()
		);

		$this->assertSame(
			$paymentMethod['last4'] ?? '',
			$object->getPaymentMethodLast4()
		);
	}

	/**
	 * @dataProvider provideStocks
	 */
	public function testStockHoldings( ?array $stocks, array $expected ): void {
		$object = new Contribution( $stocks === null ? [] : [ 'stocks' => $stocks ], [] );
		$this->assertSame( $expected, $object->getStockHoldings() );
	}

	public static function provideStocks(): array {
		return [
			'no stocks' => [ null, [] ],
			'single holding' => [
				[ 'quantity' => 1, 'tickers' => [ 'NEE' ], 'holdings' => [ [ 'ticker' => 'NEE', 'quantity' => 5 ] ] ],
				[ [ 'ticker' => 'NEE', 'quantity' => 5.0 ] ],
			],
			'multiple holdings' => [
				[
					'quantity' => 2,
					'tickers' => [ 'NEE', 'NVDA' ],
					'holdings' => [ [ 'ticker' => 'NEE', 'quantity' => 5 ], [ 'ticker' => 'NVDA', 'quantity' => 60 ] ],
				],
				[ [ 'ticker' => 'NEE', 'quantity' => 5.0 ], [ 'ticker' => 'NVDA', 'quantity' => 60.0 ] ],
			],
			'pre-holdings response' => [
				[ 'quantity' => 1, 'tickers' => [ 'TSLA' ] ],
				[ [ 'ticker' => 'TSLA', 'quantity' => null ] ],
			],
		];
	}

	public static function provideContributions(): array {
		$dir = __DIR__ . '/../Data';
		$files = glob( $dir . '/*.json' ) ?: [];

		$cases = [];

		foreach ( $files as $file ) {
			if ( str_contains( $file, 'invalid' ) ) {
				continue;
			}
			$data = json_decode(
				file_get_contents( $file ),
				true,
				512,
				JSON_THROW_ON_ERROR
			);

			foreach ( $data['contributions'] as $index => $contribution ) {
				$name = basename( $file ) . '-' . $index;
				$cases[$name] = [ $contribution ];
			}
		}

		return $cases;
	}
}
