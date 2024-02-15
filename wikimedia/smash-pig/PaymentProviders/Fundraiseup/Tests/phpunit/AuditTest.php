<?php
namespace SmashPig\PaymentProviders\Fundraiseup\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\PaymentProviders\Fundraiseup\Audit\FundraiseupAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Fundraiseup
 * @group Audit
 */
class AuditTest extends BaseSmashPigUnitTestCase {

	public function setUp() : void {
		parent::setUp();
		$ctx = Context::get();
		$config = FundraiseupTestConfiguration::instance( $ctx->getGlobalConfiguration() );
		$ctx->setProviderConfiguration( $config );
	}

	/**
	 * Normal donation
	 */
	public function testProcessDonations() {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Donations/export_donations_2023-test.csv' );
		$this->assertSame( 4, count( $output ), 'Should have found four successful donations' );
		$creditCardDonation = $output[0];
		$achDonation = $output[2];
		$expectedCreditCardDonation = [
			'gateway' => 'fundraiseup',
			'gross' => '5.64',
			'currency' => 'USD',
			'order_id' => 'DQZQFCJS',
			'gateway_txn_id' => 'ch_3NrmZLJaRQOHTfEW0zGlJw1Z',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'date' => 1695063200,
			'user_ip' => '127.0.0.1',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'street_address' => '',
			'city' => '',
			'country' => 'GB',
			'email' => 'jwales@example.org',
			'invoice_id' => 'DQZQFCJS',
			'gateway_account' => 'Wikimedia Foundation',
			'frequency_unit' => 'month',
			'frequency_interval' => 1,
			'original_currency' => 'GBP',
			'original_gross' => '4.60',
			'fee' => 0.61,
			'recurring' => '1',
			'subscr_id' => 'RCGCEFBA',
			'external_identifier' => 'SUBJJCQA',
			'start_date' => '2023-09-18T18:53:20.676Z',
			'employer' => '',
			'street_number' => '',
			'postal_code' => '',
			'state_province' => '',
			'language' => 'en-US',
			'utm_medium' => 'spontaneous',
			'utm_source' => 'fr-redir',
			'utm_campaign' => 'spontaneous',
			'type' => 'donations'
		];

		$expectedAchDonation = [
			'gateway' => 'fundraiseup',
			'gross' => '13.49',
			'currency' => 'USD',
			'order_id' => 'DGVYEEWH',
			'gateway_txn_id' => 'ch_3NrmWyJaRQOHTfEW1KdRmJIX',
			'payment_method' => 'bt',
			'payment_submethod' => 'ACH',
			'date' => 1695063056,
			'user_ip' => '127.0.0.1',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'street_address' => '',
			'city' => '',
			'country' => 'GB',
			'email' => 'jwales@example.org',
			'external_identifier' => 'SCHNECUN',
			'invoice_id' => 'DGVYEEWH',
			'gateway_account' => 'Wikimedia Foundation',
			'frequency_unit' => 'One time',
			'original_currency' => 'GBP',
			'original_gross' => '11.00',
			'fee' => 1.03,
			'recurring' => '0',
			'subscr_id' => '',
			'start_date' => '',
			'employer' => '',
			'street_number' => '',
			'postal_code' => '',
			'state_province' => '',
			'language' => 'en-US',
			'utm_medium' => 'spontaneous',
			'utm_source' => 'fr-redir',
			'utm_campaign' => 'spontaneous',
			'type' => 'donations'
		];
		$this->assertEquals( $expectedCreditCardDonation, $creditCardDonation, 'Did not parse cc donation correctly' );
		$this->assertEquals( $expectedAchDonation, $achDonation, 'Did not parse ACH donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Refunds/export_refunds_2023-test.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'fundraiseup',
			'gross' => '53.70',
			'gross_currency' => 'GBP',
			'gateway_parent_id' => 'ch_3NrfJTJaRQOHTfEW0mf8ewoL',
			'gateway_refund_id' => 'ch_3NrfJTJaRQOHTfEW0mf8ewoL',
			'type' => 'refund',
			'gateway_account' => 'TEST',
			'account' => 'Wikimedia Foundation',
			'fee' => 1.66,
			'refund' => '65.84',
			'date' => 1695047409,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * Import cancelled recurrings
	 */
	public function testProcessCancelledRecurring() {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Recurring/Cancelled/export_recurring_cancelled_2023-09-18_00-00_2023-09-22_23-59.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one cancelled recurring' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'fundraiseup',
			'gateway_account' => 'Wikimedia Foundation',
			'subscr_id' => 'RCGCEFBA',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'employer' => '',
			'email' => 'jwales@example.org',
			'external_identifier' => 'SUBJJCQA',
			'type' => 'recurring',
			'date' => 1695140630,
			'gross' => '4.60',
			'currency' => 'GBP',
			'txn_type' => 'subscr_cancel',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'utm_medium' => 'spontaneous',
			'utm_source' => 'fr-redir',
			'utm_campaign' => 'spontaneous',
			'next_sched_contribution_date' => '',
			'start_date' => 1695063200,
			'frequency_unit' => 'month',
			'cancel_date' => 1695140630,
			'create_date' => 1695063200,
			'frequency_interval' => 1,
			'country' => 'GB'
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * Import Failed recurrings
	 */
	public function testProcessFileWithWrongHeader() {
		$processor = new FundraiseupAudit();
		$this->expectException( DataFileException::class );
		$processor->parseFile( __DIR__ . '/../Data/ErroneuousExport/export_recurring_2023-09-18_00-00_2023-09-22_23-59.csv' );
	}

	/**
	 * Import Failed donations
	 */
	public function testProcessDonationFileWithWrongHeader() {
		$processor = new FundraiseupAudit();
		$this->expectException( DataFileException::class );
		$processor->parseFile( __DIR__ . '/../Data/ErroneuousExport/export_donations_2023-test.csv' );
	}

	/**
	 * Import New recurrings
	 */
	public function testProcessNewRecurring() {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Recurring/New/export_recurring_2023-09-18_00-00_2023-09-22_23-59.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one cancelled recurring' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'fundraiseup',
			'gateway_account' => 'Wikimedia Foundation',
			'subscr_id' => 'RWRYRXYC',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'employer' => '',
			'email' => 'jwales@example.org',
			'external_identifier' => 'SUBJJCQA',
			'type' => 'recurring',
			'date' => 1695035319,
			'gross' => '10.00',
			'currency' => 'GBP',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'utm_medium' => 'spontaneous',
			'utm_source' => 'fr-redir',
			'utm_campaign' => 'spontaneous',
			'next_sched_contribution_date' => 1697627269,
			'start_date' => 1695035319,
			'frequency_unit' => 'month',
			'txn_type' => 'subscr_signup',
			'create_date' => 1695035319,
			'frequency_interval' => 1,
			'country' => 'GB'
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * @covers ::getCountryFromDonationURL
	 */
	public function testProcessDonationEmptyCountryUseFallbackFromDonationURL() : void {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Donations/export_donations_2023-country-fallback-test.csv' );
		$this->assertEquals( 'GB', $output[0]['country'] );
		$this->assertEquals( 'US', $output[1]['country'] );
	}

	/**
	 * @covers ::getCountryFromDonationURL
	 */
	public function testProcessDonationEmptyCountryAndFallbackIsUnavailable() : void {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Donations/export_donations_2023-country-fallback-is-empty-test.csv' );
		$this->assertSame( '', $output[0]['country'] );
		$this->assertSame( '', $output[1]['country'] );
	}

	public function testNewRecurringEmptyCountryUseFallbackFromDonationURL() : void {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Recurring/New/export_recurring_2023-empty-country-test.csv' );
		$this->assertEquals( 'GB', $output[0]['country'] );
	}

	public function testNewRecurringEmptyCountryAndFallbackIsUnavailable() : void {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Recurring/New/export_recurring_2023-country-fallback-is-empty-test.csv' );
		$this->assertSame( '', $output[0]['country'] );
	}

	public function testFailedRecurring() : void {
		$processor = new FundraiseupAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Recurring/Failed/export_recurring_failed_2023-test.csv' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'fundraiseup',
			'gateway_account' => 'Wikimedia Foundation',
			'subscr_id' => 'RWRYRXYC',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'employer' => 'Wikpedia',
			'email' => 'jwales@example.org',
			'external_identifier' => 'SUBJJCQA',
			'type' => 'recurring',
			'date' => 1701558151,
			'gross' => '3.50',
			'currency' => 'USD',
			'payment_method' => 'apple',
			'payment_submethod' => 'mc',
			'utm_medium' => '',
			'utm_source' => 'portal',
			'utm_campaign' => 'portal',
			'next_sched_contribution_date' => '',
			'start_date' => 1701558151,
			'frequency_unit' => 'month',
			'txn_type' => 'subscr_cancel',
			'create_date' => 1701558151,
			'frequency_interval' => 1,
			'country' => 'GB',
			'cancel_date' => 1705359722,
			'cancel_reason' => 'Failed: Your card was declined.'
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}
}
