<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Test;

require_once 'AuditTest.php';

/**
 * Verify PayPal SAR audit file normalization functions
 *
 * @group PayPal
 * @group Audit
 */
class SARAuditTest extends AuditTest {

	public function testSubscrSignupClassic(): void {
		$output = $this->processFile( 'sar_classic_subscr_signup.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$expected = [
			'subscr_id' => 'S-7J123456DS987654B',
			'txn_type' => 'subscr_signup',
			'currency' => 'EUR',
			'gross' => 3.0,
			'frequency_unit' => 'month',
			'frequency_interval' => '1',
			'create_date' => 1493539200,
			'start_date' => 1493539200,
			'email' => 'recurring.donor@example.com',
			'first_name' => 'Donantus',
			'last_name' => 'Recurricus',
			'street_address' => 'Rue Faux, 41',
			'city' => 'Paris',
			'state_province' => 'Paris',
			'country' => 'FR',
			'postal_code' => '12345',
			'gateway' => 'paypal',
		];

		$this->assertEquals( $expected, $output[0] );
	}

	public function testSubscrCancelClassic(): void {
		$output = $this->processFile( 'sar_classic_subscr_cancel.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$expected = [
			'subscr_id' => 'S-7J123456DS987654B',
			'txn_type' => 'subscr_cancel',
			'currency' => 'EUR',
			'gross' => 3.0,
			'frequency_unit' => 'month',
			'frequency_interval' => '1',
			'cancel_date' => 1493539200,
			'email' => 'recurring.donor@example.com',
			'first_name' => 'Donantus',
			'last_name' => 'Recurricus',
			'street_address' => 'Rue Faux, 41',
			'city' => 'Paris',
			'state_province' => 'Paris',
			'country' => 'FR',
			'postal_code' => '12345',
			'gateway' => 'paypal',
		];

		$this->assertEquals( $expected, $output[0] );
	}

}
