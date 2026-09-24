<?php

namespace SmashPig\PaymentProviders\Overflow\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Overflow\Deposit;

class DepositTest extends TestCase {

	/**
	 * @dataProvider provideFixtures
	 */
	public function testDeposit( string $filePath ): void {
		$data = $this->loadFixture( $filePath );

		$deposit = new Deposit( $data );

		$this->assertNotEmpty( $deposit->getId() );
		$this->assertSame( 'PAID', $deposit->getStatus() );
		$this->assertSame( 'AUTOMATED', $deposit->getType() );
		$this->assertGreaterThan( 0, $deposit->getAmountInMinorUnits() );
		$this->assertNotEmpty( $deposit->getArrivalAt() );
		$this->assertNotEmpty( $deposit->getStatementDescriptor() );
	}

	/**
	 * @dataProvider provideFixtures
	 */
	public function testSummary( string $filePath ): void {
		$data = $this->loadFixture( $filePath );

		$deposit = new Deposit( $data );

		$this->assertSame(
			$data['summary']['data']['contributions']['count'],
			$deposit->getSummaryContributionCount()
		);

		$this->assertSame(
			$data['summary']['data']['contributions']['grossInCents'],
			$deposit->getSummaryGrossAmountInMinorUnits()
		);

		$this->assertSame(
			$data['summary']['data']['contributions']['feesInCents'],
			$deposit->getSummaryFeeAmountInMinorUnits()
		);

		$this->assertSame(
			$data['summary']['data']['contributions']['totalInCents'],
			$deposit->getSummaryNetAmountInMinorUnits()
		);
	}

	/**
	 * @dataProvider provideFixtures
	 */
	public function testContributions( string $filePath ): void {
		$data = $this->loadFixture( $filePath );

		$deposit = new Deposit( $data );

		$this->assertSameSize(
			$data['contributions'],
			$deposit->getContributions()
		);

		foreach ( $deposit->getContributions() as $contribution ) {
			$this->assertNotEmpty( $contribution->getId() );
			$this->assertGreaterThan( 0, $contribution->getAmountInMinorUnits() );
			$this->assertNotEmpty( $contribution->getLineItemReferenceId() );
		}
	}

	/**
	 * @dataProvider provideFixtures
	 */
	public function testLineItems( string $filePath ): void {
		$data = $this->loadFixture( $filePath );

		$deposit = new Deposit( $data );

		$this->assertSameSize(
			$data['deposit']['lineItems'],
			$deposit->getLineItems()
		);
	}

	public function testValidDeposit(): void {
		$data = $this->loadFixture( 'deposit.json' );
		$deposit = new Deposit( $data );
		$deposit->validate();
		$this->assertTrue( true );
	}

	public function testValidMultipleContributionDeposit(): void {
		$data = $this->loadFixture( 'deposit-2-contributions.json' );
		$deposit = new Deposit( $data );
		$deposit->validate();
		$this->assertTrue( true );
	}

	public function testValidationFailsWhenLineItemsDoNotMatchDeposit(): void {
		$data = $this->loadFixture( 'invalid-deposit-cancelled-contribution.json' );
		$deposit = new Deposit( $data );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'contribution gross 34192 does not match summary gross 119861' );
		$deposit->validate();
	}

	public function testValidationFailsWhenContributionGrossDoesNotMatchSummary(): void {
		$data = $this->loadFixture( 'deposit-2-contributions.json' );
		$data['contributions'][0]['contribution']['amount'] += 1;
		$deposit = new Deposit( $data );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'contribution gross' );
		$deposit->validate();
	}

	public function testValidationFailsWhenSummaryGrossMinusFeesDoesNotMatchTotal(): void {
		$data = $this->loadFixture( 'deposit-2-contributions.json' );
		$data['summary']['data']['contributions']['feesInCents']++;
		$deposit = new Deposit( $data );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'summary gross' );
		$deposit->validate();
	}

	public function testValidationFailsWhenSummaryTotalDoesNotMatchDeposit(): void {
		$data = $this->loadFixture( 'deposit-2-contributions.json' );
		$data['summary']['data']['contributions']['totalInCents']++;
		$deposit = new Deposit( $data );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'summary total' );
		$deposit->validate();
	}

	public static function provideFixtures(): array {
		$dir = __DIR__ . '/../Data';
		$files = glob( $dir . '/*.json' ) ?: [];

		$cases = [];
		foreach ( $files as $file ) {
			if ( str_contains( $file, 'invalid' ) ) {
				continue;
			}
			$cases[basename( $file )] = [ $file ];
		}

		return $cases;
	}

	private function loadFixture( string $filePath ): array {
		$basePath = __DIR__ . '/../Data/';
		if ( !str_starts_with( $filePath, '/' ) ) {
			$filePath = $basePath . $filePath;
		}
		try {
			$data = json_decode(
				file_get_contents( $filePath ),
				true,
				512,
				JSON_THROW_ON_ERROR
			);
		} catch ( \JsonException $e ) {
			$this->fail( 'invalid json in ' . $filePath . ' ' . $e->getMessage() );
		}

		$this->assertIsArray( $data );

		return $data;
	}
}
