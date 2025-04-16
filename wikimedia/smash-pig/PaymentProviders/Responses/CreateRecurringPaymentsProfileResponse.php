<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class CreateRecurringPaymentsProfileResponse
 * @package SmashPig\PaymentProviders
 */
class CreateRecurringPaymentsProfileResponse extends PaymentProviderExtendedResponse {

	/**
	 * A unique identifier for future reference to the details of this recurring payment.
	 * Up to 14 single-byte alphanumeric characters.
	 *
	 * @var string|null
	 */
	protected ?string $profileId = null;

	/**
	 * @param string $profileId
	 * @return static
	 */
	public function setProfileId( string $profileId ): self {
		$this->profileId = $profileId;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getProfileId(): ?string {
		return $this->profileId;
	}
}
