<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Chariot\Audit\DonationsAudit;

class AuditTest extends TestCase {

	/**
	 * @dataProvider provideAuditCsvFiles
	 */
	public function testParseFileReturnsParsedRows( string $filePath ): void {
		$parser = new DonationsAudit();
		$rows = $parser->parseFile( $filePath );

		$this->assertIsArray( $rows );
		$this->assertNotEmpty( $rows, 'Expected parsed rows for fixture ' . basename( $filePath ) );

		foreach ( $rows as $row ) {
			$this->assertIsArray( $row );
			$this->assertArrayHasKey( 'gateway', $row );
			$this->assertArrayHasKey( 'audit_file_gateway', $row );
			$this->assertArrayHasKey( 'type', $row );
			$this->assertArrayHasKey( 'date', $row );

			$this->assertSame( 'chariot', $row['gateway'] );
			$this->assertSame( 'chariot', $row['audit_file_gateway'] );
			$this->assertIsInt( $row['date'] );

			if ( array_key_exists( 'settled_date', $row ) ) {
				$this->assertIsInt( $row['settled_date'] );
			}
		}
	}

	/**
	 * @dataProvider provideAuditCsvFiles
	 */
	public function testParseFileIncludesExpectedColumnsWhenPresent( string $filePath ): void {
		$parser = new DonationsAudit();
		$rows = $parser->parseFile( $filePath );

		$allKeys = [];
		foreach ( $rows as $row ) {
			foreach ( array_keys( $row ) as $key ) {
				$allKeys[$key] = true;
			}
		}

		$this->assertArrayHasKey( 'backend_processor', $allKeys );
		$this->assertArrayHasKey( 'backend_processor_txn_id', $allKeys );
		$this->assertArrayHasKey( 'original_currency', $allKeys );
		$this->assertArrayHasKey( 'settlement_batch_reference', $allKeys );
	}

	public function testParseDonorAdvisedFundFile(): void {
		$rows = $this->parseFile();
		$donation = $rows[1];
		$this->assertEquals( 'The Bart & Lisa Giving Account', $donation['donor_advised_fund_name'] );
	}

	public static function provideAuditCsvFiles(): array {
		$dir = __DIR__ . '/Data';
		$files = glob( $dir . '/*.csv' ) ?: [];

		$cases = [];
		foreach ( $files as $file ) {
			$cases[basename( $file )] = [ $file ];
		}

		return $cases;
	}

	private function getDataDirectory(): string {
		return __DIR__ . '/Data';
	}

	/**
	 * @return array
	 */
	public function parseFile(): array {
		$fixture = $this->getDataDirectory() . '/20260502081216-Groundswell-1000.00-deposit_01kqkvxnj5mc751be13egn6j6p.csv';
		$parser = new DonationsAudit();
		return $parser->parseFile( $fixture );
	}

}
