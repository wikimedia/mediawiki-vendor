<?php

namespace SmashPig\PaymentProviders\Chariot;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class Donation {

	private array $donation;

	public function __construct( array $donation ) {
		$this->donation = $donation;
		$this->validateAmounts();
	}

	/**
	 * Get a value from the donation array at the given path.
	 *
	 * This function also enforces us updating our metadata as to which
	 * fields are used, helping us to in-code document.
	 *
	 * @param string $path
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	private function getValue( string $path, $default = null ): mixed {
		ChariotObjectMetadata::assertDonationFieldIsUsed( $path );

		$value = $this->donation;
		foreach ( explode( '.', $path ) as $key ) {
			if ( !is_array( $value ) || !array_key_exists( $key, $value ) ) {
				return $default;
			}
			$value = $value[$key];
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		return $value;
	}

	public function getDonor(): array {
		return $this->donation['attribution']['primary_donor'] ?? [];
	}

	public function getProperties(): array {
		return $this->donation['properties'] ?? [];
	}

	public function getPartnerName(): string {
		if ( !empty( $this->getProperties()['Partner'] ) ) {
			$partner = $this->getProperties()['Partner'];
		} elseif ( !empty( $this->donation['attribution']['joint_donor']['full_name'] ) ) {
			$partner = $this->donation['attribution']['joint_donor']['full_name'];
		} else {
			$partner = (string)( $this->donation['partner_full_name'] ?? $this->donation['partner'] ?? '' );
		}
		return $this->normalizePersonalField( $partner );
	}

	public function getFirstName(): string {
		return $this->normalizePersonalField( (string)( $this->getDonor()['first_name'] ?? '' ) );
	}

	public function getLastName(): string {
		return $this->normalizePersonalField( (string)( $this->getDonor()['last_name'] ?? '' ) );
	}

	public function getFullName(): string {
		return $this->normalizePersonalField( (string)( $this->getDonor()['full_name'] ?? '' ) );
	}

	public function getPrefix(): string {
		return $this->normalizePersonalField( (string)( $this->donation['prefix'] ?? $this->getProperties()['Prefix'] ?? '' ) );
	}

	public function getSuffix(): string {
		return $this->normalizePersonalField( (string)( $this->donation['suffix'] ?? $this->getProperties()['Suffix'] ?? '' ) );
	}

	public function getEmail(): string {
		return $this->normalizePersonalField( (string)( $this->donation['donor_email'] ?? $this->getDonor()['email'] ?? '' ) );
	}

	public function getPhone(): string {
		return $this->normalizePersonalField( $this->getValue( 'donor_phone', '' ) ?: $this->getValue( 'attribution.primary_donor.phone', '' ) );
	}

	public function getAddress(): array {
		return $this->getDonor()['address'] ?? [];
	}

	public function getCountry(): string {
		return $this->normalizePersonalField( (string)( $this->getProperties()['Country'] ?? $this->getAddress()['country'] ?? '' ) );
	}

	public function getPostalCode(): string {
		return $this->normalizePersonalField( (string)( $this->getAddress()['postal_code'] ?? '' ) );
	}

	public function getCity(): string {
		return $this->normalizePersonalField( (string)( $this->getAddress()['city'] ?? '' ) );
	}

	public function getStateProvince(): string {
		return $this->normalizePersonalField( trim( (string)( $this->getAddress()['state'] ?? '' ) ) );
	}

	public function getStreetAddress(): string {
		return $this->normalizePersonalField( (string)( $this->getAddress()['line1'] ?? '' ) );
	}

	public function getSupplementalAddress(): string {
		return $this->normalizePersonalField( (string)( $this->getAddress()['line2'] ?? '' ) );
	}

	public function getGiftSource(): string {
		if ( !empty( $this->getValue( 'properties.Gift Type' ) ) ) {
			return (string)( $this->getValue( 'properties.Gift Type' ) );
		}
		return (string)$this->getValue( 'corporate_match.source' );
	}

	public function getAppeal(): string {
		return (string)$this->getValue( 'properties.Appeal Code' );
	}

	public function getBankingInstitution(): string {
		return (string)( $this->getValue( 'donor_advised_fund_grant.organization_name' ) );
	}

	public function getDonorAdvisedFundName(): string {
		return (string)( $this->getValue( 'donor_advised_fund_grant.donor_fund_name' ) );
	}

	public function getDonorAdvisedFundData(): array {
		return $this->donation['donor_advised_fund_grant'] ?? [];
	}

	public function getDafPayUrl(): string {
		return (string)( $this->getValue( 'dafpay_url' ) ?: $this->getValue( 'initiation.web_location_url' ) );
	}

	public function getDafPayFrequency(): string {
		return (string)( $this->getValue( 'dafpay_frequency' ) ?: $this->getValue( 'initiation.frequency' ) );
	}

	public function getDafPayTrackingId(): string {
		return (string)( $this->getValue( 'dafpay_tracking_id' ) ?: $this->getValue( 'initiation.dafpay_tracking_id' ) );
	}

	public function getDafPayType(): string {
		return (string)( $this->getValue( 'dafpay_type' ) ?: $this->getValue( 'initiation.channel' ) );
	}

	/**
	 * Is the donation from a donor advised fund. Note we treat it
	 * as daf OR matching gift, prioritising the matching gift.
	 * @return bool
	 */
	public function isDonorAdvisedFundGrant(): bool {
		return ( $this->getDonorAdvisedFundData() && !$this->isMatchingGift() );
	}

	public function getPlatformName(): string {
		return (string)( $this->donation['platform']['name'] ?? '' );
	}

	public function getCheckNumber(): string {
		return (string)( $this->getValue( 'properties.Check Number' ) );
	}

	public function getExchangeRate(): ?float {
		if ( !$this->getValue( 'platform.metadata.Foreign Exchange Rate' ) ) {
			return null;
		}
		return (float)( $this->getValue( 'platform.metadata.Foreign Exchange Rate' ) );
	}

	public function getCorporateMatchData(): array {
		return $this->donation['corporate_match'] ?? [];
	}

	public function isMatchingGift(): bool {
		return !empty( $this->getCorporateMatchData() );
	}

	public function getMatchingGiftOrganization(): string {
		return (string)( $this->getCorporateMatchData()['company_name'] ?? '' );
	}

	/**
	 * @return string
	 */
	public function getNote(): string {
		$donation = $this->donation;
		$platform = $donation['platform'] ?? [];
		$metadata = $platform['metadata'] ?? [];
		$donor = $donation['attribution']['primary_donor'] ?? [];
		$acknowledgement = trim( (string)( $metadata['Acknowledgement'] ?? '' ) );
		$note = (string)( $donation['note'] ?? '' );
		if ( $note === '' ) {
			$note = (string)( $donation['purpose'] ?? '' );
		}
		if ( $note === '' ) {
			$note = (string)( $metadata['Description'] ?? '' );
		}
		if ( $acknowledgement !== '' && strcasecmp( $acknowledgement, $this->normalizePersonalField( (string)( $donor['full_name'] ?? '' ) ) ) !== 0 ) {
			$note = $note !== ''
				? $note . ' | Acknowledgement: ' . $acknowledgement
				: 'Acknowledgement: ' . $acknowledgement;
		}
		return $note;
	}

	/**
	 * Normalize personal fields.
	 *
	 * @param string $value
	 * @return string
	 */
	private function normalizePersonalField( string $value ): string {
		if ( $this->isFullNameFundName() ) {
			// If the full name field has the fund rather than the individual we
			// can discard all individual details.
			return '';
		}
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		if ( in_array( strtolower( $value ), [ 'not shared by donor', 'not shared' ], true ) ) {
			return '';
		}

		return $value;
	}

	public function getOriginalCurrency(): string {
		return $this->donation['currency'];
	}

	public function getOriginalTotalAmountInMinorUnits(): int {
		return (int)( $this->donation['amount_gross'] ?? 0 );
	}

	public function getOriginalFeeAmountInMinorUnits(): int {
		if ( empty( $this->donation['amount_fee'] ) ) {
			return 0;
		}
		return (int)-( $this->donation['amount_fee'] );
	}

	public function getOriginalNetAmountInMinorUnits(): int {
		return (int)( $this->donation['amount_net'] ?? 0 );
	}

	public function getOriginalIndividualGiftTotalAmountInMinorUnits(): int {
		return (int)( $this->donation['individual_gift_amount'] ?? 0 );
	}

	/**
	 * Allocate the donation fee between the individual and matching gift.
	 *
	 * If a matching gift exists, the entire fee is attributed to it.
	 * Otherwise, the fee is attributed to the individual gift.
	 *
	 * @return int
	 */
	public function getOriginalIndividualGiftFeeAmountInMinorUnits(): int {
		return $this->getOriginalMatchingGiftTotalAmountInMinorUnits() ? 0 : $this->getOriginalFeeAmountInMinorUnits();
	}

	public function getOriginalIndividualGiftNetAmountInMinorUnits(): int {
		return $this->getOriginalIndividualGiftTotalAmountInMinorUnits() + $this->getOriginalIndividualGiftFeeAmountInMinorUnits();
	}

	public function getOriginalMatchingGiftTotalAmountInMinorUnits(): int {
		return (int)( $this->getCorporateMatchData()['match_amount'] ?? '0' );
	}

	public function getOriginalMatchingGiftFeeAmountInMinorUnits(): int {
		return $this->getOriginalMatchingGiftTotalAmountInMinorUnits() ? $this->getOriginalFeeAmountInMinorUnits() : 0;
	}

	public function getOriginalMatchingGiftNetAmountInMinorUnits(): int {
		return $this->getOriginalMatchingGiftTotalAmountInMinorUnits() + $this->getOriginalMatchingGiftFeeAmountInMinorUnits();
	}

	public function getOriginalTotalAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalTotalAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalFeeAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalFeeAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalNetAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalNetAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalIndividualGiftTotalAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalIndividualGiftTotalAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalIndividualGiftFeeAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalIndividualGiftFeeAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalIndividualGiftNetAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalIndividualGiftNetAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalMatchingGiftTotalAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalMatchingGiftTotalAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalMatchingGiftFeeAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalMatchingGiftFeeAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getOriginalMatchingGiftNetAmountRounded(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getOriginalMatchingGiftNetAmountInMinorUnits(),
			$this->getOriginalCurrency()
		);
	}

	public function getSettledTotalAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getConvertedAmountRounded(
			$this->getOriginalTotalAmountInMinorUnits(),
			$exchangeRate,
			$settledCurrency
		);
	}

	public function getSettledFeeAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getConvertedAmountRounded(
			$this->getOriginalFeeAmountInMinorUnits(),
			$exchangeRate,
			$settledCurrency
		);
	}

	public function getSettledNetAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		$net = (float)$this->getSettledTotalAmountRounded( $exchangeRate, $settledCurrency )
			+ (float)$this->getSettledFeeAmountRounded( $exchangeRate, $settledCurrency );

		return CurrencyRoundingHelper::round(
			$net,
			$settledCurrency
		);
	}

	public function getSettledIndividualGiftTotalAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getConvertedAmountRounded(
			$this->getOriginalIndividualGiftTotalAmountInMinorUnits(),
			$exchangeRate,
			$settledCurrency
		);
	}

	public function getSettledIndividualGiftFeeAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getConvertedAmountRounded(
			$this->getOriginalIndividualGiftFeeAmountInMinorUnits(),
			$exchangeRate,
			$settledCurrency
		);
	}

	public function getSettledIndividualGiftNetAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getConvertedAmountRounded(
			$this->getOriginalIndividualGiftNetAmountInMinorUnits(),
			$exchangeRate,
			$settledCurrency
		);
	}

	public function getSettledMatchingGiftTotalAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getDerivedMatchingGiftAmountRounded(
			$this->getSettledTotalAmountRounded( $exchangeRate, $settledCurrency ),
			$this->getSettledIndividualGiftTotalAmountRounded( $exchangeRate, $settledCurrency ),
			$settledCurrency
		);
	}

	public function getSettledMatchingGiftNetAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getDerivedMatchingGiftAmountRounded(
			$this->getSettledNetAmountRounded( $exchangeRate, $settledCurrency ),
			$this->getSettledIndividualGiftNetAmountRounded( $exchangeRate, $settledCurrency ),
			$settledCurrency
		);
	}

	public function getSettledMatchingGiftFeeAmountRounded( float $exchangeRate, string $settledCurrency ): string {
		return $this->getDerivedMatchingGiftAmountRounded(
			$this->getSettledFeeAmountRounded( $exchangeRate, $settledCurrency ),
			$this->getSettledIndividualGiftFeeAmountRounded( $exchangeRate, $settledCurrency ),
			$settledCurrency
		);
	}

	/**
	 * Derive the matching gift amount from the settled total and settled
	 * individual amounts so that they always reconcile.
	 */
	private function getDerivedMatchingGiftAmountRounded(
		string $settledTotal,
		string $settledIndividual,
		string $currency
	): string {
		$matching = (float)$settledTotal - (float)$settledIndividual;

		return CurrencyRoundingHelper::round(
			$matching,
			$currency
		);
	}

	private function getConvertedAmountRounded( int $amount, float $exchangeRate, string $settledCurrency ): string {
		$originalMajor = CurrencyRoundingHelper::getAmountInMajorUnits(
			$amount,
			$this->getOriginalCurrency()
		);

		$settledMajor = (float)$originalMajor * $exchangeRate;

		return CurrencyRoundingHelper::round(
			$settledMajor,
			$settledCurrency
		);
	}

	public function validateAmounts(): void {
		$this->assertAmountsEqual(
			$this->getOriginalNetAmountInMinorUnits(),
			$this->getOriginalTotalAmountInMinorUnits() +
			$this->getOriginalFeeAmountInMinorUnits(),
			'Total + (negative)  fee must equal net'
		);

		$this->assertAmountsEqual(
			$this->getOriginalTotalAmountInMinorUnits(),
			$this->getOriginalIndividualGiftTotalAmountInMinorUnits() +
			$this->getOriginalMatchingGiftTotalAmountInMinorUnits(),
			'Individual + matching totals must equal total'
		);

		$this->assertAmountsEqual(
			$this->getOriginalFeeAmountInMinorUnits(),
			$this->getOriginalIndividualGiftFeeAmountInMinorUnits() +
			$this->getOriginalMatchingGiftFeeAmountInMinorUnits(),
			'Individual + matching fees must equal fee'
		);

		$this->assertAmountsEqual(
			$this->getOriginalNetAmountInMinorUnits(),
			$this->getOriginalIndividualGiftNetAmountInMinorUnits() +
			$this->getOriginalMatchingGiftNetAmountInMinorUnits(),
			'Individual + matching net amounts must equal net'
		);
	}

	private function assertAmountsEqual(
		int $expected,
		int $actual,
		string $description
	): void {
		if ( $expected !== $actual ) {
			throw new \UnexpectedValueException(
				sprintf(
					'%s: expected %d, got %d',
					$description,
					$expected,
					$actual
				)
			);
		}
	}

	/**
	 * Is the full name actually the name of the Donor Advised Fund.
	 *
	 * The full_name should hold the name of the individual donor but chariot data
	 * hygiene is not always consistent, so we check if the name is actually the
	 * same as the func name or ends in a 'fundy string' - ' Account' or ' Fund'
	 * and ignore individual fields if it is.
	 *
	 * @return bool
	 */
	public function isFullNameFundName(): bool {
		$name = $this->getValue( 'attribution.primary_donor.full_name' );
		$donorAdvisedFundName = $this->getDonorAdvisedFundName();
		if ( !$donorAdvisedFundName || !$name ) {
			return false;
		}
		if ( $name === $donorAdvisedFundName
		  || str_ends_with( $name, ' Account' ) || str_ends_with( $name, ' Fund' )

		) {
			return true;
		}
		return false;
	}

}
