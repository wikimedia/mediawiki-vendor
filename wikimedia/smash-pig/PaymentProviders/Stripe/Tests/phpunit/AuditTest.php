<?php

namespace SmashPig\PaymentProviders\Stripe\Test;

use SmashPig\PaymentProviders\Stripe\Audit\StripeAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Audit
 * @group Stripe
 */
class AuditTest extends BaseSmashPigUnitTestCase {

	public function testParseSettlementReportRunCsv(): void {
		$processor = new StripeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_report.csv' );

		$this->assertCount( 3, $output );
		$this->assertSame( '24315.1', $output[0]['order_id'] );
		$this->assertSame( 'pi_123', $output[0]['backend_processor_txn_id'] );
		$this->assertSame( 'stripe', $output[0]['audit_file_gateway'] );
		$this->assertSame( 'stripe', $output[0]['backend_processor'] );
		$this->assertSame( 'acct_live', $output[0]['gateway_account'] );
		$this->assertSame( 'donation', $output[0]['type'] );
		$this->assertSame( 'po_test123', $output[0]['settlement_batch_reference'] );
		$this->assertSame( '0.95', $output[0]['settled_fee_amount'] );
		$this->assertSame( '24.05', $output[0]['settled_net_amount'] );
		$this->assertSame( 'USD', $output[0]['settled_currency'] );
		$this->assertArrayNotHasKey( 'balance_transaction_id', $output[0] );
		$this->assertArrayNotHasKey( 'source_id', $output[0] );
		$this->assertArrayNotHasKey( 'reporting_category', $output[0] );
		$this->assertSame( 'refund', $output[1]['type'] );
		$this->assertSame( 'payout', $output[2]['type'] );
	}

	public function testParseSettlementApiCsv(): void {
		$processor = new StripeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_api.csv' );

		$this->assertCount( 2, $output );
		$this->assertSame( '24315.1', $output[0]['order_id'] );
		$this->assertSame( 'pi_123', $output[0]['backend_processor_txn_id'] );
		$this->assertSame( 'stripe', $output[0]['backend_processor'] );
		$this->assertSame( 'acct_live', $output[0]['gateway_account'] );
		$this->assertSame( 'po_123', $output[0]['settlement_batch_reference'] );
		$this->assertArrayNotHasKey( 'balance_transaction_id', $output[0] );
		$this->assertSame( 'payout', $output[1]['type'] );
	}

	public function testParsePaymentsActivityCsv(): void {
		$processor = new StripeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/payments_activity.csv' );

		$this->assertCount( 2, $output );
		$this->assertSame( '24315.1', $output[0]['order_id'] );
		$this->assertSame( 'pi_aaa', $output[0]['backend_processor_txn_id'] );
		$this->assertSame( 'stripe', $output[0]['backend_processor'] );
		$this->assertSame( 'acct_live', $output[0]['gateway_account'] );
		$this->assertSame( 'chargeback', $output[1]['type'] );
	}
}
