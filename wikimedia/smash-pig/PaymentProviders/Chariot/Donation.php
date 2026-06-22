<?php

namespace SmashPig\PaymentProviders\Chariot;

class Donation {

	private array $donation;

	public function __construct( array $donation ) {
		$this->donation = $donation;
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
		return $this->normalizePersonalField( (string)( $this->donation['donor_phone'] ?? '' ) );
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
		if ( !empty( $this->getProperties()['Gift Type'] ) ) {
			return (string)( $this->getProperties()['Gift Type'] );
		}
		return $this->donation['corporate_match']['source'] ?? '';
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
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		if ( in_array( strtolower( $value ), [ 'not shared by donor', 'not shared' ], true ) ) {
			return '';
		}

		return $value;
	}

}
