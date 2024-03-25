<?php

namespace SmashPig\PaymentData;

class DonorDetails {
	/**
	 * The donor first name
	 *
	 * @var string|null
	 */
	protected ?string $firstName;

	/**
	 * The donor last name
	 *
	 * @var string|null
	 */
	protected ?string $lastName;

	/**
	 * Full name as a single string, as given by some payment providers
	 *
	 * @var string|null
	 */
	protected ?string $fullName;

	/**
	 * Venmo customer id as a single string, as given by some payment providers
	 *
	 * @var string|null
	 */
	protected ?string $customerId;

	/**
	 * Venmo user name as a single string, as given by some payment providers
	 *
	 * @var string|null
	 */
	protected ?string $userName;

	/**
	 * The donor email
	 *
	 * @var string|null
	 */
	protected ?string $email;

	/**
	 * The donor phone
	 *
	 * @var string|null
	 */
	protected ?string $phone;

	/**
	 * Donor's billing address
	 *
	 * @var Address|null
	 */
	protected ?Address $billingAddress;

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
	 * @param string|null $customerId
	 * @return DonorDetails
	 */
	public function setCustomerId( ?string $customerId ): DonorDetails {
		$this->customerId = $customerId;
		return $this;
	}

	/**
	 * @param string|null $userName
	 * @return DonorDetails
	 */
	public function setUserName( ?string $userName ): DonorDetails {
		$this->userName = $userName;
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
	 * @param Address|null $billingAddress
	 * @return DonorDetails
	 */
	public function setBillingAddress( ?Address $billingAddress ): DonorDetails {
		$this->billingAddress = $billingAddress;
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
	 * Get venmo user name from payment response
	 * @return string|null
	 */
	public function getUserName(): ?string {
		return $this->userName;
	}

	/**
	 * Get venmo customer id from payment response
	 * @return string|null
	 */
	public function getCustomerId(): ?string {
		return $this->customerId;
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

	/**
	 * Get donor billing address from payment response
	 * @return Address|null
	 */
	public function getBillingAddress(): ?Address {
		return $this->billingAddress;
	}
}
