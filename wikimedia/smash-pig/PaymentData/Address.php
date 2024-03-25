<?php

namespace SmashPig\PaymentData;

class Address {

	protected ?string $streetAddress;
	protected ?string $countryCode;
	protected ?string $city;
	protected ?string $postalCode;
	protected ?string $stateOrProvinceCode;

	public function getStreetAddress(): ?string {
		return $this->streetAddress;
	}

	public function setStreetAddress( ?string $streetAddress ): Address {
		$this->streetAddress = $streetAddress;
		return $this;
	}

	public function getCountryCode(): ?string {
		return $this->countryCode;
	}

	public function setCountryCode( ?string $countryCode ): Address {
		$this->countryCode = $countryCode;
		return $this;
	}

	public function getCity(): ?string {
		return $this->city;
	}

	public function setCity( ?string $city ): Address {
		$this->city = $city;
		return $this;
	}

	public function getPostalCode(): ?string {
		return $this->postalCode;
	}

	public function setPostalCode( ?string $postalCode ): Address {
		$this->postalCode = $postalCode;
		return $this;
	}

	public function getStateOrProvinceCode(): ?string {
		return $this->stateOrProvinceCode;
	}

	public function setStateOrProvinceCode( ?string $stateOrProvinceCode ): Address {
		$this->stateOrProvinceCode = $stateOrProvinceCode;
		return $this;
	}

}
