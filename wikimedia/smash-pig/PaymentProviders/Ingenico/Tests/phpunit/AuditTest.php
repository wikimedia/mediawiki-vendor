<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use SmashPig\PaymentProviders\Ingenico\Audit\IngenicoAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Audit
 * @group Ingenico
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/donation.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'globalcollect',
			'gross' => 3.00,
			'contribution_tracking_id' => '5551212',
			'currency' => 'USD',
			'order_id' => '987654321',
			'installment' => 1,
			'gateway_txn_id' => '987654321',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'date' => 1501368968,
			'user_ip' => '111.222.33.44',
			'first_name' => 'Arthur',
			'last_name' => 'Aardvark',
			'street_address' => '1111 Fake St',
			'city' => 'Denver',
			'country' => 'US',
			'email' => 'dutchman@flying.net',
			'invoice_id' => '5551212.68168',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Normal donation
	 */
	public function testProcessBPayDonation() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/donation_bpay.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'globalcollect',
			'gross' => 10,
			'contribution_tracking_id' => '255777921',
			'currency' => 'AUD',
			'order_id' => '657000777333',
			'installment' => 1,
			'gateway_txn_id' => '657000777333',
			'payment_method' => 'obt',
			'payment_submethod' => 'bpay',
			'date' => 1582070400,
			'invoice_id' => '255777921',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Recurring donation
	 */
	public function testProcessRecurring() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/recurring.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one recurring donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'globalcollect',
			'gross' => 3.00,
			'contribution_tracking_id' => '5551212',
			'currency' => 'USD',
			'order_id' => '987654321',
			'installment' => 3,
			'recurring' => 1,
			'gateway_txn_id' => '987654321',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'date' => 1501368968,
			'user_ip' => '111.222.33.44',
			'first_name' => 'Arthur',
			'last_name' => 'Aardvark',
			'street_address' => '1111 Fake St',
			'city' => 'Denver',
			'country' => 'US',
			'email' => 'dutchman@flying.net',
			'invoice_id' => '5551212.68168',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => '5551212',
			'date' => 1500942220,
			'gross' => 100,
			'gateway_parent_id' => '123456789',
			'gateway_refund_id' => '123456789',
			'installment' => 1,
			'gross_currency' => 'USD',
			'type' => 'refund',
			'invoice_id' => '5551212.29660',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * Audit transactions relating to new ingenico connect api donations still contain
	 * the globalcollect-style OrderID's e.g. 123456789 vs the newer (and longer)
	 * format of 000000123401234567890000100001
	 *
	 * The parser detects the gateway type and transforms accordingly if needed.
	 * @see IngenicoAudit::getConnectPaymentId()
	 *
	 * In this test, we confirm that transformation takes place.
	 */
	public function testParseIngenicoConnectRefund() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund_ingenico_connect.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'ingenico',
			'contribution_tracking_id' => '5551212',
			'date' => 1500942220,
			'gross' => 100,
			'gateway_parent_id' => '000000123401234567890000100001',
			'gateway_refund_id' => '000000123401234567890000100001',
			'installment' => 1,
			'gross_currency' => 'USD',
			'type' => 'refund',
			'invoice_id' => '5551212.12',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * Refunds made before Ingenico collected the money from the issuing bank
	 * have some differences in the record.
	 */
	public function testParseUncollectedRefund() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund_uncollected.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'ingenico',
			'contribution_tracking_id' => '65544422',
			'date' => 1550002118,
			'gross' => 3,
			'gateway_parent_id' => '000000123440009995550000100001',
			'gateway_refund_id' => '000000123440009995550000100001',
			'installment' => 1,
			'gross_currency' => 'USD',
			'type' => 'refund',
			'invoice_id' => '65544422.2',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * Now try a recurring refund of installment higher than 1
	 */
	public function testProcessRecurringRefund() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/recurringrefund.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'globalcollect', // TODO: switch to ingenico for Connect
			'contribution_tracking_id' => '5551212',
			'date' => 1500942220,
			'gross' => 100,
			'gateway_parent_id' => '123456789-2',
			'gateway_refund_id' => '123456789-2',
			'installment' => 2,
			'gross_currency' => 'USD',
			'type' => 'refund',
			'invoice_id' => '5551212.29660',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessChargeback() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/chargeback.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one chargeback' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => '5551212',
			'date' => 1495023569,
			'gross' => 200,
			'gateway_parent_id' => '5167046621',
			'gateway_refund_id' => '5167046621',
			'installment' => 1,
			'gross_currency' => 'USD',
			'type' => 'chargeback',
			'invoice_id' => '5551212.29660',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}

	/**
	 * We get some refunds in a weird sparse format with OrderID zero and no
	 * TransactionDateTime. At least get the ct_id and a date out of them.
	 */
	public function testProcessSparseRefund() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/sparseRefund.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => '48987654',
			'date' => 1503964800,
			'gross' => 15,
			'gateway_parent_id' => '0', // We'll need to find it by ct_id
			'gateway_refund_id' => '0', // And we'll need to fill in this field
			'installment' => '', // EffortID came in blank too
			'gross_currency' => 'EUR',
			'type' => 'refund',
			'invoice_id' => '48987654.12345',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * Donation via new API, gets new gateway
	 */
	public function testProcessConnectDonation() {
		$processor = new IngenicoAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/connectdonation.xml.gz' );
		$this->assertSame( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'ingenico',
			'gross' => 3.00,
			'contribution_tracking_id' => '5551212',
			'currency' => 'USD',
			'order_id' => '987654321',
			'installment' => 1,
			'gateway_txn_id' => '000000123409876543210000100001',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'date' => 1501368968,
			'first_name' => 'Arthur',
			'last_name' => 'Aardvark',
			'street_address' => '1111 Fake St',
			'city' => 'Denver',
			'country' => 'US',
			'email' => 'dutchman@flying.net',
			'invoice_id' => '5551212.1',
			'gateway_account' => '1234',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

}
