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

	public function testSettledNetAmountReconcilesWithRoundedTotalAndFee(): void {
		$donation = new Donation( [
			'amount_gross' => 400,
			'amount_net' => 392,
			'amount_fee' => 8,
			'individual_gift_amount' => 200,
			'corporate_match' => [
				'match_amount' => 200,
			],
			'currency' => 'CAD',
		] );

		$exchangeRate = 0.698200;

		$this->assertSame( '2.79', $donation->getSettledTotalAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '-0.06', $donation->getSettledFeeAmountRounded( $exchangeRate, 'USD' ) );

		// Regression test: rounded amounts must reconcile.
		$this->assertSame(
			'2.73',
			$donation->getSettledNetAmountRounded( $exchangeRate, 'USD' )
		);
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

	public function testGetsDonorAdvisedFundValues(): void {
		$donation = new Donation( [
			'donor_advised_fund_grant' => [
				'organization_name' => 'My Foundation',
				'donor_fund_name' => 'Daisy Mouse Fund',
			],
		] );

		$this->assertSame( 'My Foundation', $donation->getBankingInstitution() );
		$this->assertSame( 'Daisy Mouse Fund', $donation->getDonorAdvisedFundName() );
		$this->assertTrue( $donation->isDonorAdvisedFundGrant() );
	}

	public function testGetsEmptyDonorAdvisedFundValuesWhenMissing(): void {
		$donation = new Donation( [] );

		$this->assertSame( '', $donation->getBankingInstitution() );
		$this->assertSame( '', $donation->getDonorAdvisedFundName() );
		$this->assertSame( [], $donation->getDonorAdvisedFundData() );
		$this->assertFalse( $donation->isDonorAdvisedFundGrant() );
	}

	/**
	 * @dataProvider fullNameFundNameProvider
	 */
	public function testIgnoresPersonalFieldsWhenFullNameIsFundName(
		string $fullName,
		string $fundName
	): void {
		$donation = new Donation( [
			'attribution' => [
				'primary_donor' => [
					'full_name' => $fullName,
					'first_name' => 'Jane',
					'last_name' => 'Smith',
					'email' => 'jane@example.org',
				],
			],
			'donor_advised_fund_grant' => [
				'donor_fund_name' => $fundName,
			],
		] );

		$this->assertTrue( $donation->isFullNameFundName() );

		$this->assertSame( '', $donation->getFullName() );
		$this->assertSame( '', $donation->getFirstName() );
		$this->assertSame( '', $donation->getLastName() );
		$this->assertSame( '', $donation->getEmail() );
	}

	public function fullNameFundNameProvider(): array {
		return [
			'exact_match' => [
				'Acme Charitable Fund',
				'Acme Charitable Fund',
			],
			'fund_suffix' => [
				'John Smith Fund',
				'Schwab Charitable',
			],
			'account_suffix' => [
				'John Smith Account',
				'Fidelity Charitable',
			],
		];
	}

	public function testDoesNotIgnorePersonalFieldsForNormalDonor(): void {
		$donation = new Donation( [
			'attribution' => [
				'primary_donor' => [
					'full_name' => 'Jane Smith',
					'first_name' => 'Jane',
					'last_name' => 'Smith',
					'email' => 'jane@example.org',
				],
			],
			'donor_advised_fund_grant' => [
				'donor_fund_name' => 'Acme Charitable Fund',
			],
		] );

		$this->assertFalse( $donation->isFullNameFundName() );

		$this->assertSame( 'Jane Smith', $donation->getFullName() );
		$this->assertSame( 'Jane', $donation->getFirstName() );
		$this->assertSame( 'Smith', $donation->getLastName() );
		$this->assertSame( 'jane@example.org', $donation->getEmail() );
	}

	public function testGetsPlatformName(): void {
		$donation = new Donation( [
			'platform' => [
				'name' => 'Benevity',
			],
		] );

		$this->assertSame( 'Benevity', $donation->getPlatformName() );
	}

	public function testGetsEmptyPlatformNameWhenMissing(): void {
		$donation = new Donation( [] );

		$this->assertSame( '', $donation->getPlatformName() );
	}

	public function testGetsCorporateMatchValues(): void {
		$donation = new Donation( [
			'amount_fee' => 0,
			'amount_net' => 400,
			'amount_gross' => 400,
			'individual_gift_amount' => 0,
			'corporate_match' => [
				'company_name' => 'Disney',
				'match_amount' => 400,
				'program_name' => 'Payroll Match',
				'source' => 'Payroll',
			],
		] );

		$this->assertSame(
			[
				'company_name' => 'Disney',
				'match_amount' => 400,
				'program_name' => 'Payroll Match',
				'source' => 'Payroll',
			],
			$donation->getCorporateMatchData()
		);
		$this->assertTrue( $donation->isMatchingGift() );
		$this->assertSame( 'Disney', $donation->getMatchingGiftOrganization() );
		$this->assertSame( 400, $donation->getOriginalMatchingGiftTotalAmountInMinorUnits() );
	}

	public function testGetsEmptyCorporateMatchValuesWhenMissing(): void {
		$donation = new Donation( [] );

		$this->assertSame( [], $donation->getCorporateMatchData() );
		$this->assertFalse( $donation->isMatchingGift() );
		$this->assertSame( '', $donation->getMatchingGiftOrganization() );
		$this->assertSame( 0, $donation->getOriginalMatchingGiftTotalAmountInMinorUnits() );
	}

	public function testMatchingGiftCanHaveZeroAmount(): void {
		$donation = new Donation( [
			'corporate_match' => [
				'company_name' => 'Disney',
				'match_amount' => 0,
			],
		] );

		$this->assertTrue( $donation->isMatchingGift() );
		$this->assertSame( 'Disney', $donation->getMatchingGiftOrganization() );
		$this->assertSame( 0, $donation->getOriginalMatchingGiftTotalAmountInMinorUnits() );
	}

	public function testGetsOriginalAmountsInMinorUnits(): void {
		$donation = new Donation( [
			'amount_fee' => 87,
			'amount_net' => 2013,
			'amount_gross' => 2100,
			'individual_gift_amount' => 2100,
		] );

		$this->assertSame( -87, $donation->getOriginalFeeAmountInMinorUnits() );
		$this->assertSame( 2013, $donation->getOriginalNetAmountInMinorUnits() );
		$this->assertSame( 2100, $donation->getOriginalTotalAmountInMinorUnits() );
	}

	public function testGetsOriginalAmountsInMinorUnitsAsZeroWhenMissing(): void {
		$donation = new Donation( [ 'amount_fee' => '' ] );

		$this->assertSame( 0, $donation->getOriginalFeeAmountInMinorUnits() );
		$this->assertSame( 0, $donation->getOriginalNetAmountInMinorUnits() );
		$this->assertSame( 0, $donation->getOriginalTotalAmountInMinorUnits() );
	}

	public function testGetsSettledAmountsRounded(): void {
		$donation = new Donation( [
			'amount_fee' => 87,
			'amount_net' => 2013,
			'amount_gross' => 2100,
			'individual_gift_amount' => 2100,
			'currency' => 'USD',
		] );

		$exchangeRate = 0.712197;

		$this->assertSame( '-0.62', $donation->getSettledFeeAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '14.34', $donation->getSettledNetAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '14.96', $donation->getSettledTotalAmountRounded( $exchangeRate, 'USD' ) );
	}

	public function testGetSettledAmountsRoundedForJpy(): void {
		$donation = new Donation( [
			'amount_fee' => 0,
			'amount_net' => 1000,
			'amount_gross' => 1000,
			'currency' => 'JPY',
			'individual_gift_amount' => 1000,
		] );

		$exchangeRate = 0.006084;

		$this->assertSame( '0.00', $donation->getSettledFeeAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '6.08', $donation->getSettledNetAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '6.08', $donation->getSettledTotalAmountRounded( $exchangeRate, 'USD' ) );
	}

	public function testGetSettledAmountRoundedToZeroWhenMissing(): void {
		$donation = new Donation( [ 'amount_fee' => '', 'currency' => 'USD' ] );

		$this->assertSame( '0.00', $donation->getSettledFeeAmountRounded( 0.712197, 'USD' ) );
		$this->assertSame( '0.00', $donation->getSettledNetAmountRounded( 0.712197, 'USD' ) );
		$this->assertSame( '0.00', $donation->getSettledTotalAmountRounded( 0.712197, 'USD' ) );
	}

	public function testGetIndividualGiftAmountsWithoutMatchingGift(): void {
		$donation = new Donation( [
			'amount_fee' => 87,
			'amount_net' => 2013,
			'currency' => 'USD',
			'amount_gross' => 2100,
			'individual_gift_amount' => 2100,
		] );

		$this->assertSame( 2100, $donation->getOriginalIndividualGiftTotalAmountInMinorUnits() );
		$this->assertSame( -87, $donation->getOriginalIndividualGiftFeeAmountInMinorUnits() );
		$this->assertSame( 2013, $donation->getOriginalIndividualGiftNetAmountInMinorUnits() );

		$this->assertSame( 0, $donation->getOriginalMatchingGiftTotalAmountInMinorUnits() );
		$this->assertSame( 0, $donation->getOriginalMatchingGiftFeeAmountInMinorUnits() );
		$this->assertSame( 0, $donation->getOriginalMatchingGiftNetAmountInMinorUnits() );

		$this->assertSame(
			$donation->getOriginalTotalAmountInMinorUnits(),
			$donation->getOriginalIndividualGiftTotalAmountInMinorUnits()
			+ $donation->getOriginalMatchingGiftTotalAmountInMinorUnits()
		);

		$this->assertSame(
			$donation->getOriginalFeeAmountInMinorUnits(),
			$donation->getOriginalIndividualGiftFeeAmountInMinorUnits()
			+ $donation->getOriginalMatchingGiftFeeAmountInMinorUnits()
		);

		$this->assertSame(
			$donation->getOriginalNetAmountInMinorUnits(),
			$donation->getOriginalIndividualGiftNetAmountInMinorUnits()
			+ $donation->getOriginalMatchingGiftNetAmountInMinorUnits()
		);
	}

	public function testGetsIndividualAndMatchingGiftAmounts(): void {
		$donation = new Donation( [
			'amount_fee' => 87,
			'amount_net' => 2413,
			'amount_gross' => 2500,
			'currency' => 'USD',
			'individual_gift_amount' => 2100,
			'corporate_match' => [
				'match_amount' => 400,
			],
		] );

		$this->assertSame( 2100, $donation->getOriginalIndividualGiftTotalAmountInMinorUnits() );
		$this->assertSame( 0, $donation->getOriginalIndividualGiftFeeAmountInMinorUnits() );
		$this->assertSame( 2100, $donation->getOriginalIndividualGiftNetAmountInMinorUnits() );

		$this->assertSame( 400, $donation->getOriginalMatchingGiftTotalAmountInMinorUnits() );
		$this->assertSame( -87, $donation->getOriginalMatchingGiftFeeAmountInMinorUnits() );
		$this->assertSame( 313, $donation->getOriginalMatchingGiftNetAmountInMinorUnits() );
	}

	public function testGetSettledIndividualAndMatchingGiftAmountsRounded(): void {
		$donation = new Donation( [
			'amount_fee' => 87,
			'amount_net' => 2413,
			'amount_gross' => 2500,
			'individual_gift_amount' => 2100,
			'currency' => 'USD',
			'corporate_match' => [
				'match_amount' => 400,
			],
		] );

		$exchangeRate = 0.712197;

		$this->assertSame( '14.96', $donation->getSettledIndividualGiftTotalAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '0.00', $donation->getSettledIndividualGiftFeeAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '14.96', $donation->getSettledIndividualGiftNetAmountRounded( $exchangeRate, 'USD' ) );

		$this->assertSame( '2.84', $donation->getSettledMatchingGiftTotalAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '-0.62', $donation->getSettledMatchingGiftFeeAmountRounded( $exchangeRate, 'USD' ) );
		$this->assertSame( '2.22', $donation->getSettledMatchingGiftNetAmountRounded( $exchangeRate, 'USD' ) );
	}

	/**
	 * Test that matching_gift and individual_gift add up to total when adjustment required.
	 *
	 * In this scenario the 2 gifts individually convert to 3.37, but together they convert
	 * to 6.75. We need to check that the total_amount, net_amount and fee_amount are correct in
	 * this case (ie the total & net wind up being each adjusted by one cent)
	 *
	 * @return void
	 */
	public function testMatchingGiftSettledAmountsRoundToSettledTotal(): void {
		$donation = new Donation( [
			'amount_gross' => 600,
			'amount_net' => 600,
			'amount_fee' => 0,
			'individual_gift_amount' => 300,
			'corporate_match' => [ 'match_amount' => 300 ],
			'currency' => 'EUR',
		] );

		$exchangeRate = 1.1248;

		$this->assertSame(
			'6.75',
			$donation->getSettledTotalAmountRounded( $exchangeRate, 'USD' )
		);

		$this->assertSame(
			'3.37',
			$donation->getSettledIndividualGiftTotalAmountRounded( $exchangeRate, 'USD' )
		);

		$this->assertSame(
			'3.38',
			$donation->getSettledMatchingGiftTotalAmountRounded( $exchangeRate, 'USD' )
		);
		$this->assertSame(
			'6.75',
			$donation->getSettledNetAmountRounded( $exchangeRate, 'USD' )
		);

		$this->assertSame(
			'3.37',
			$donation->getSettledIndividualGiftNetAmountRounded( $exchangeRate, 'USD' )
		);

		$this->assertSame(
			'3.38',
			$donation->getSettledMatchingGiftNetAmountRounded( $exchangeRate, 'USD' )
		);

		$this->assertSame(
			'0.00',
			$donation->getSettledIndividualGiftFeeAmountRounded( $exchangeRate, 'USD' )
		);

		$this->assertSame(
			'0.00',
			$donation->getSettledMatchingGiftFeeAmountRounded( $exchangeRate, 'USD' )
		);
	}

}
