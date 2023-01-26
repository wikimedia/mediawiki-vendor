<?php

namespace SmashPig\PaymentData;

class DonorDetails {
	/**
	 * The donor first name
	 *
	 * @var string|null
	 */
	protected $firstName;

	/**
	 * The donor last name
	 *
	 * @var string|null
	 */
	protected $lastName;

	/**
	 * Full name as a single string, as given by some payment providers
	 *
	 * @var string|null
	 */
	protected $fullName;

	/**
	 * The donor email
	 *
	 * @var string|null
	 */
	protected $email;

	/**
	 * The donor phone
	 *
	 * @var string|null
	 */
	protected $phone;

	/**
	 * @param string|null $firstName
	 * @return DonorDetails
	 */
	public function setFirstName( ?string $firstName ): DonorDetails {
		$this->firstName = $firstName;
		return $this;
	}

	/**
	 * @param string|null $lastName
	 * @return DonorDetails
	 */
	public function setLastName( ?string $lastName ): DonorDetails {
		$this->lastName = $lastName;
		return $this;
	}

	/**
	 * @param string|null $fullName
	 * @return DonorDetails
	 */
	public function setFullName( ?string $fullName ): DonorDetails {
		$this->fullName = $fullName;
		return $this;
	}

	/**
	 * @param string|null $email
	 * @return DonorDetails
	 */
	public function setEmail( ?string $email ): DonorDetails {
		$this->email = $email;
		return $this;
	}

	/**
	 * @param string|null $phone
	 * @return DonorDetails
	 */
	public function setPhone( ?string $phone ): DonorDetails {
		$this->phone = $phone;
		return $this;
	}

	/**
	 * Get donor first name from payment response
	 * @return string
	 */
	public function getFirstName(): ?string {
		return $this->firstName;
	}

	/**
	 * Get donor last name from payment response
	 * @return string|null
	 */
	public function getLastName(): ?string {
		return $this->lastName;
	}

	/**
	 * Get full name from payment response
	 * @return string|null
	 */
	public function getFullName(): ?string {
		return $this->fullName;
	}

	/**
	 * Get donor email from payment response
	 * @return string
	 */
	public function getEmail(): ?string {
		return $this->email;
	}

	/**
	 * Get donor email from payment response
	 * @return string
	 */
	public function getPhone(): ?string {
		return $this->phone;
	}
}
