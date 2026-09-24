<?php

namespace SmashPig\PaymentProviders\Overflow\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Overflow\Audit\DepositsAudit;

class DepositsAuditTest extends TestCase {

	public function testParseFileReturnsOneRowPerContributionPlusPayoutRow(): void {
		$parser = new DepositsAudit();
		$rows = $parser->parseFile( $this->getDataDirectory() . '/deposit-2-contributions.json' );

		$this->assertCount( 3, $rows );
		$contributionRows = array_slice( $rows, 0, 2 );
		foreach ( $contributionRows as $row ) {
			$this->assertSame( 'overflow', $row['gateway'] );
			$this->assertSame( 'overflow', $row['audit_file_gateway'] );
			$this->assertSame( 'donation', $row['type'] );
			$this->assertSame( 'Foundation', $row['gateway_account'] );
			$this->assertIsInt( $row['date'] );
			$this->assertIsInt( $row['settled_date'] );
		}
		// The fixture has one STOCK and one DAF contribution - Overflow's
		// 'DAF' payment method should come through as CiviCRM's 'DAFpay'.
		$this->assertSame( 'Stock', $rows[0]['payment_method'] );
		$this->assertSame( 'DAFpay', $rows[1]['payment_method'] );

		$payoutRow = $rows[2];
		$this->assertSame( 'payout', $payoutRow['type'] );
		$this->assertSame( '691bc9e0f8be59262e7d5ad4', $payoutRow['gateway_txn_id'] );
		// Must equal the sum of the contribution rows' settled_net_amount,
		// for BaseAuditProcessor::getValidBatches() to verify the batch.
		$this->assertSame( '7539.56', $payoutRow['settled_total_amount'] );
	}

	public function testParseFileNormalizesSingleContribution(): void {
		$parser = new DepositsAudit();
		$rows = $parser->parseFile( $this->getDataDirectory() . '/deposit.json' );
		$row = $rows[0];

		$this->assertSame( '6a7c9a6e7027ca16774e6f76', $row['gateway_txn_id'] );
		$this->assertSame( 'Foundation', $row['gateway_account'] );
		$this->assertSame( 'Stock', $row['payment_method'] );
		$this->assertSame( 'OVRFLW-S322222226JMLQ2', $row['settlement_batch_reference'] );
		$this->assertSame( '7231.05', $row['original_total_amount'] );
		$this->assertSame( '7231.05', $row['settled_total_amount'] );
		$this->assertSame( '-72.31', $row['settled_fee_amount'] );
		$this->assertSame( '7158.74', $row['settled_net_amount'] );
		$this->assertSame( 'Jane', $row['first_name'] );
		$this->assertSame( 'Mouse', $row['last_name'] );
		$this->assertSame( 'jane.mouse@example.org', $row['email'] );
		$this->assertSame( 'Minneapolis', $row['city'] );
		$this->assertSame( 'TSLA', $row['stock_ticker'] );
		$this->assertSame( 1.0, $row['stock_quantity'] );
	}

	/**
	 * GetReport.php stamps 'gateway_account' onto the raw JSON at fetch time
	 * (since Foundation and Endowment deposits can land in the same audit
	 * run) - it should come through on the normalized row unchanged, for the
	 * CRM importer to key financial_type off.
	 */
	public function testParseFilePropagatesGatewayAccount(): void {
		$data = json_decode( file_get_contents( $this->getDataDirectory() . '/deposit.json' ), true, 512, JSON_THROW_ON_ERROR );
		$data['gateway_account'] = 'Endowment';

		$path = tempnam( sys_get_temp_dir(), 'overflow-deposit-' );
		file_put_contents( $path, json_encode( $data, JSON_THROW_ON_ERROR ) );

		try {
			$parser = new DepositsAudit();
			$rows = $parser->parseFile( $path );
			$this->assertSame( 'Endowment', $rows[0]['gateway_account'] );
		} finally {
			unlink( $path );
		}
	}

	/**
	 * The fixture has a contribution that was cancelled after being included
	 * in the deposit's line items (its 'amount' has gone missing), so the
	 * deposit no longer reconciles and should fail loudly rather than being
	 * silently partially imported.
	 */
	public function testParseFileThrowsWhenDepositFailsValidation(): void {
		$parser = new DepositsAudit();
		$this->expectException( \RuntimeException::class );
		$parser->parseFile( $this->getDataDirectory() . '/invalid-deposit-cancelled-contribution.json' );
	}

	private function getDataDirectory(): string {
		return __DIR__ . '/../Data';
	}
}
