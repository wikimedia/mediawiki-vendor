<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Test;

require_once 'AuditTest.php';

/**
 * Verify PayPal audit file normalization functions
 *
 * @group PayPal
 * @group Audit
 */
class TRRAuditTest extends AuditTest {

	public function testProcessExpressCheckoutDonation(): void {
		$output = $this->processFile( 'trr_express_checkout_donation.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$expected = [
			'last_name' => 'Who',
			'city' => 'Whoville',
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'JPY',
			'postal_code' => '97211',
			'date' => 1488477595,
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'state_province' => 'OR',
			'gross' => 150.0,
			'first_name' => 'Cindy Lou',
			'fee' => 43.0,
			'original_net_amount' => '107',
			'original_fee_amount' => -43.0,
			'settled_fee_amount' => -43.0,
			'settled_net_amount' => '107',
			'gateway_txn_id' => '1V551844CE5526421',
			'country' => 'US',
			'payment_submethod' => '',
			'supplemental_address_1' => '',
			'settled_date' => 1488477595,
			'email' => 'donor@generous.net',
			'street_address' => '321 Notta Boulevard',
			'contribution_tracking_id' => '46239229',
			'order_id' => '46239229.1',
			'settlement_batch_reference' => '20170302',
			'settled_total_amount' => 150.0,
			'exchange_rate' => 1,
			'settled_currency' => 'JPY',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	public function testProcessWithHeaderAndFooterRows(): void {
		$output = $this->processFile( 'trr_express_with_non_data_rows.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$expected = [
			'last_name' => 'Who',
			'city' => 'Whoville',
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'JPY',
			'postal_code' => '97211',
			'date' => 1488477595,
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'state_province' => 'OR',
			'gross' => 150.0,
			'first_name' => 'Cindy Lou',
			'fee' => 43.0,
			'original_fee_amount' => -43.0,
			'original_net_amount' => 107,
			'settled_fee_amount' => -43.0,
			'gateway_txn_id' => '1V551844CE5526421',
			'country' => 'US',
			'payment_submethod' => '',
			'supplemental_address_1' => '',
			'settled_date' => 1488477595,
			'email' => 'donor@generous.net',
			'street_address' => '321 Notta Boulevard',
			'contribution_tracking_id' => 46239229,
			'order_id' => '46239229.1',
			'settled_total_amount' => 150.0,
			'settled_net_amount' => '107',
			'exchange_rate' => 1,
			'settled_currency' => 'JPY',
			'settlement_batch_reference' => '20170302',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	public function testExpressCheckoutDonationDeniedNotEmitted(): void {
		// Port of: test_ec_donation_denied_not_sent (normalization-only interpretation)
		$output = $this->processFile( 'trr_express_checkout_donation_denied.csv' );
		$this->assertCount( 0, $output, 'Denied donation should not be emitted' );
	}

	public function testProcessExpressCheckoutRecurringDonation(): void {
		// Port of: test_ec_recurring_donation_send
		$output = $this->processFile( 'trr_express_checkout_recurring_donation.csv' );
		$this->assertCount( 1, $output, 'Should have found one recurring row' );

		$expected = [
			'txn_type' => 'subscr_payment',
			'subscr_id' => 'I-SS5RD7POSD46',
			'last_name' => 'Who',
			'city' => '',
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'JPY',
			'postal_code' => '',
			'date' => 1488634565,
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'state_province' => '',
			'gross' => 150.0,
			'first_name' => 'Cindy Lou',
			'fee' => 43.0,
			'original_fee_amount' => -43.0,
			'settled_fee_amount' => -43.0,
			'settled_total_amount' => '150',
			'settled_net_amount' => '107',
			'original_net_amount' => '107',
			'gateway_txn_id' => '4JH2438EE9876546W',
			'country' => '',
			'payment_submethod' => '',
			'supplemental_address_1' => '',
			'settled_date' => 1488634565,
			'email' => 'donor@generous.net',
			'street_address' => '',
			'contribution_tracking_id' => 45931681,
			'order_id' => '45931681.1',
			'exchange_rate' => 1,
			'settled_currency' => 'JPY',
			'settlement_batch_reference' => '20170304',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	public function testProcessExpressCheckoutRefund(): void {
		// Port of: test_ec_refund_send (normalization-only)
		$output = $this->processFile( 'trr_express_checkout_refund.csv' );
		$this->assertCount( 1, $output, 'Should have found one refund row' );

		$expected = [
			'last_name' => 'Who',
			'city' => 'Whoville',
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'JPY',
			'postal_code' => '97211',
			'date' => 1490200499,
			'gateway_refund_id' => '3HD08833MR473623T',
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'state_province' => 'OR',
			'gross' => 150.0,
			'first_name' => 'Cindy Lou',
			'fee' => 43.0,
			'original_fee_amount' => 43.0,
			'settled_fee_amount' => 43.0,
			'settled_total_amount' => -150.0,
			'settled_net_amount' => '-107',
			'original_net_amount' => '-107',
			'gateway_txn_id' => '3HD08833MR473623T',
			'gross_currency' => 'JPY',
			'country' => 'US',
			'payment_submethod' => '',
			'supplemental_address_1' => '',
			'settled_date' => 1490200499,
			'gateway_parent_id' => '1V551844CE5526421',
			'type' => 'refund',
			'email' => 'donor@generous.net',
			'street_address' => '321 Notta Boulevard',
			'contribution_tracking_id' => 46239229,
			'order_id' => '46239229.1',
			'exchange_rate' => 1,
			'settled_currency' => 'JPY',
			'settlement_batch_reference' => '20170322',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	public function testProcessExpressCheckoutRecurringRefund(): void {
		// Port of: test_ec_recurring_refund_send (normalization-only)
		$output = $this->processFile( 'trr_express_checkout_recurring_refund.csv' );
		$this->assertCount( 1, $output, 'Should have found one recurring refund row' );

		$expected = [
			'last_name' => 'Who',
			'city' => '',
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'JPY',
			'postal_code' => '',
			'date' => 1490200431,
			'gateway_refund_id' => '8WG23468CX793000L',
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'state_province' => '',
			'gross' => 150.0,
			'first_name' => 'Cindy Lou',
			'fee' => 43.0,
			'original_fee_amount' => 43.0,
			'settled_fee_amount' => 43.0,
			'settled_total_amount' => -150.0,
			'settled_net_amount' => '-107',
			'original_net_amount' => '-107',
			'gateway_txn_id' => '8WG23468CX793000L',
			'gross_currency' => 'JPY',
			'country' => '',
			'payment_submethod' => '',
			'supplemental_address_1' => '',
			'settled_date' => 1490200431,
			'gateway_parent_id' => '4JH2438EE9876546W',
			'type' => 'refund',
			'email' => 'donor@generous.net',
			'street_address' => '',
			'contribution_tracking_id' => 45931681,
			'order_id' => '45931681.1',
			'exchange_rate' => 1,
			'settled_currency' => 'JPY',
			'settlement_batch_reference' => '20170322',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	public function testTagGiveLively(): void {
		$output = $this->processFile( 'trr_give_lively.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$expected = [
			'last_name' => 'Who',
			'city' => 'Whoville',
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'JPY',
			'postal_code' => '97211',
			'date' => 1488477595,
			'gateway' => 'paypal',
			'audit_file_gateway' => 'paypal',
			'state_province' => 'OR',
			'gross' => 150.0,
			'first_name' => 'Cindy Lou',
			'fee' => 43.0,
			'original_fee_amount' => -43.0,
			'settled_fee_amount' => -43.0,
			'settled_total_amount' => 150.0,
			'settled_net_amount' => '107',
			'original_net_amount' => '107',
			'gateway_txn_id' => '1V551844CE5526421',
			'country' => 'US',
			'payment_submethod' => '',
			'supplemental_address_1' => '',
			'settled_date' => 1488477595,
			'email' => 'donor@generous.net',
			'street_address' => '321 Notta Boulevard',
			'order_id' => '',
			'contribution_tracking_id' => null,
			'exchange_rate' => 1,
			'settled_currency' => 'JPY',
			'settlement_batch_reference' => '20170302',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	/**
	 * Note that the python version of this puts in a contact ID but in the
	 * SmashPig version that is done higher up the stack.
	 *
	 * @return void
	 */
	public function testTagGivingFund(): void {
		// givingfund_cid = 1234567
		// givingfund_emails includes ppgfuspay@paypalgivingfund.org
		$output = $this->processFile( 'trr_giving_fund.csv' );
		$this->assertCount( 1, $output, 'Should have found one Giving Fund row' );

		$expected = [
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'JPY',
			'date' => 1488477595,
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'gross' => 150.0,
			'fee' => 43.0,
			'original_fee_amount' => -43.0,
			'settled_fee_amount' => -43.0,
			'settled_total_amount' => 150.0,
			'settled_net_amount' => '107',
			'original_net_amount' => '107',
			'gateway_txn_id' => '1V551844CE5526421',
			'payment_submethod' => '',
			'settled_date' => 1488477595,
			'contribution_tracking_id' => '46239229',
			'order_id' => '46239229.1',
			'email' => 'ppgfuspay@paypalgivingfund.org',
			'street_address' => '321 Notta Boulevard',
			'supplemental_address_1' => '',
			'city' => 'Whoville',
			'state_province' => 'OR',
			'postal_code' => '97211',
			'country' => 'US',
			'last_name' => 'Who',
			'first_name' => 'Cindy Lou',
			'exchange_rate' => 1,
			'settled_currency' => 'JPY',
			'settlement_batch_reference' => '20170302',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	public function testGravyDonation(): void {
		$output = $this->processFile( 'trr_gravy_donation.csv' );
		$expected = [
			'first_name' => 'Firsty',
			'last_name' => 'Lasty',
			'city' => 'Test',
			'payment_method' => 'paypal',
			'gateway_status' => 'S',
			'currency' => 'BRL',
			'postal_code' => '123456',
			'date' => 1754587116,
			'gateway' => 'gravy',
			'audit_file_gateway' => 'paypal',
			'state_province' => 'Test',
			'gross' => 32,
			'fee' => 3.92,
			'original_net_amount' => '28.08',
			'original_fee_amount' => -3.92,
			'settled_total_amount' => 32,
			'settled_fee_amount' => -3.92,
			'settled_net_amount' => '28.08',
			'gateway_txn_id' => '12da3381-d52e-47ec-be26-49a81cb31dfe',
			'backend_processor_txn_id' => '12345678JN486083U',
			'backend_processor' => 'paypal',
			'payment_orchestrator_reconciliation_id' => '2ZZZxx7YYYYqqQysK53Fpm',
			'country' => 'US',
			'payment_submethod' => '',
			'supplemental_address_1' => '',
			'settled_date' => 1754587116,
			'email' => 'blahbla@example.com',
			'street_address' => 'Testy',
			'contribution_tracking_id' => 987654329,
			'order_id' => '987654329.1',
			'settlement_batch_reference' => '20250807',
			'exchange_rate' => 1,
			'settled_currency' => 'BRL',
		];

		$this->assertEquals( $expected, $output[0] );
	}

}
