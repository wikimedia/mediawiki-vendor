<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Chariot\Donation;

class DonationTest extends TestCase {

	/**
	 * @param array $donation
	 *
	 * @dataProvider partnerDataProvider
	 *
	 * @return void
	 */
	public function testGetPartnerName( array $donation ): void {
		$this->assertEquals( 'Mickey Mouse', ( new Donation( $donation ) )->getPartnerName() );
	}

	public function partnerDataProvider(): array {
		return [
			'set_in_property' => [
				'donation' => [
					'properties' => [ 'Partner' => 'Mickey Mouse' ],
				],
			],
			'paypal_grants_style' => [
				'donation' => [
					'attribution' => [
						'joint_donor' => [
							'email' => '',
							'full_name' => 'Mickey Mouse',
						],
						'primary_donor' => [
							'email' => '',
							'full_name' => 'Minnie Mouse',
						],
					],
				],
			],
		];
	}

	/**
	 * @param array $donation
	 * @param string $expected
	 *
	 * @dataProvider giftSourceDataProvider
	 *
	 * @return void
	 */
	public function testGetGiftSource( array $donation, string $expected ): void {
		$this->assertEquals( $expected, ( new Donation( $donation ) )->getGiftSource() );
	}

	public function giftSourceDataProvider(): array {
		return [
			'set_in_property' => [
				'donation' => [
					'properties' => [ 'Gift Type' => 'Corporate Match' ],
				],
				'expected' => 'Corporate Match',
			],
			'corporate_match_source' => [
				'donation' => [
					'corporate_match' => [
						'source' => 'Benevity',
					],
				],
				'expected' => 'Benevity',
			],
			'property_takes_precedence' => [
				'donation' => [
					'properties' => [ 'Gift Type' => 'Payroll Giving' ],
					'corporate_match' => [
						'source' => 'Benevity',
					],
				],
				'expected' => 'Payroll Giving',
			],
			'missing_source' => [
				'donation' => [],
				'expected' => '',
			],
		];
	}

}
