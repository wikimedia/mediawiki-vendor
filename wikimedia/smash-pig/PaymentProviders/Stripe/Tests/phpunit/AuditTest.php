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

		$this->assertCount( 7, $output );

		// Some generic ones.
		$this->assertSame( 'stripe', $output[0]['audit_file_gateway'] );
		$this->assertSame( 'stripe', $output[0]['backend_processor'] );
		$this->assertSame( 'acct_live', $output[0]['gateway_account'] );
		$this->assertSame( 'USD', $output[1]['original_currency'] );
		$this->assertSame( 'USD', $output[1]['currency'] );

		// Check order ID from any of the 3 places it might be in.
		$this->assertSame( '24315.1', $output[0]['order_id'] );
		$this->assertSame( '24316.1', $output[1]['order_id'] );
		$this->assertSame( '24317.1', $output[2]['order_id'] );

		// Check payment_orchestrator_reconciliation_id from either of the 3 places it might be in.
		$this->assertSame( '', $output[0]['payment_orchestrator_reconciliation_id'] );
		$this->assertSame( 'abcd', $output[1]['payment_orchestrator_reconciliation_id'] );
		$this->assertSame( 'efgh', $output[2]['payment_orchestrator_reconciliation_id'] );

		$this->assertSame( '00000000-0000-0000-0000-000000851fcf', $output[1]['gateway_txn_id'] );
		$this->assertSame( '00000000-0000-0000-0000-00000093e8bb', $output[2]['gateway_txn_id'] );
		$this->assertSame( 'pi_123', $output[0]['backend_processor_txn_id'] );

		$this->assertSame( 'donation', $output[0]['type'] );
		$this->assertSame( 'po_test123', $output[0]['settlement_batch_reference'] );
		$this->assertSame( '-0.95', $output[0]['settled_fee_amount'] );
		$this->assertSame( '24.05', $output[0]['settled_net_amount'] );
		$this->assertSame( 'USD', $output[0]['settled_currency'] );
		$this->assertArrayNotHasKey( 'balance_transaction_id', $output[0] );
		$this->assertArrayNotHasKey( 'source_id', $output[0] );
		$this->assertArrayNotHasKey( 'reporting_category', $output[0] );
		$this->assertSame( 'refund', $output[1]['type'] );

		// Check out the fee rows
		$this->assertSame( 'fee', $output[3]['type'] );
		$this->assertSame( '-0.5', $output[3]['settled_fee_amount'] );
		$this->assertSame( '-0.5', $output[3]['settled_net_amount'] );
		$this->assertSame( '0.0', $output[3]['settled_total_amount'] );
		$this->assertSame( 'stripe', $output[3]['gateway'] );
		$this->assertSame( 'stripe', $output[3]['backend_processor'] );
		$this->assertSame( 'txn_fee_01', $output[3]['backend_processor_txn_id'] );
		$this->assertSame( 'po_test123', $output[3]['settlement_batch_reference'] );
		$this->assertSame( 'fee', $output[4]['type'] );
		$this->assertSame( 'chargeback_reversed', $output[5]['type'] );
		$this->assertSame( 'payout', $output[6]['type'] );

		// customer_* columns are absent from this fixture, so no contact
		// fields should be bubbled up.
		$this->assertArrayNotHasKey( 'email', $output[0] );
		$this->assertArrayNotHasKey( 'full_name', $output[0] );
	}

	public function testParseSettlementReportWithCustomerDataCsv(): void {
		$processor = new StripeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_report_with_customer_data.csv' );

		$this->assertCount( 3, $output );

		// Full name splits into first/last, and the address fields bubble up.
		$this->assertSame( 'Homer Simpson', $output[0]['full_name'] );
		$this->assertSame( 'homer@example.com', $output[0]['email'] );
		$this->assertSame( '555-1234', $output[0]['phone'] );
		$this->assertSame( '742 Evergreen Terrace', $output[0]['street_address'] );
		$this->assertSame( 'Apt 2', $output[0]['supplemental_address_1'] );
		$this->assertSame( 'Springfield', $output[0]['city'] );
		$this->assertSame( 'IL', $output[0]['state_province'] );
		$this->assertSame( '62701', $output[0]['postal_code'] );
		$this->assertSame( 'US', $output[0]['country'] );

		// A single-word name has no last name.
		$this->assertSame( 'Cher', $output[1]['full_name'] );
		$this->assertSame( 'cher@example.com', $output[1]['email'] );
	}

	public function testParseGivingBasketCsv(): void {
		$processor = new StripeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_report_giving_basket.csv' );

		$this->assertCount( 2, $output );

		// An ordinary card donation processed via Give Lively's platform is
		// not the Giving Basket bundle - it has full customer data and
		// should not be flagged as an organization gift.
		$this->assertArrayNotHasKey( 'organization_name', $output[0] );
		$this->assertSame( 'Homer Simpson', $output[0]['full_name'] );
		$this->assertSame( 'cc', $output[0]['payment_method'] );

		// The Giving Basket transfer has no per-donor billing details, so
		// it is flagged with the sending organization's name, and its
		// Stripe Connect payment_method_type is mapped to the canonical
		// PaymentMethod::STRIPE value.
		$this->assertSame( 'Give Lively', $output[1]['organization_name'] );
		$this->assertSame( 'cc', $output[1]['payment_method'] );
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
