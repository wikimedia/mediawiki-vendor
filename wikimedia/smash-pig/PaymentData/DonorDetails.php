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
	 * @return void
	 */
	public function setFirstName( string $firstName ): void {
		$this->firstName = $firstName;
	}

	/**
	 * @param string|null $lastName
	 * @return void
	 */
	public function setLastName( string $lastName ): void {
		$this->lastName = $lastName;
	}

	/**
	 * @param string|null $email
	 * @return void
	 */
	public function setEmail( string $email ): void {
		$this->email = $email;
	}

	/**
	 * @param string|null $phone
	 * @return void
	 */
	public function setPhone( string $phone ): void {
		$this->phone = $phone;
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
	 * @return string
	 */
	public function getLastName(): ?string {
		return $this->lastName;
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
