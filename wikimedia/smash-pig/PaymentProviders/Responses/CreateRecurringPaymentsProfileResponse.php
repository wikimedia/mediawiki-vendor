<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class CreateRecurringPaymentsProfileResponse
 * @package SmashPig\PaymentProviders
 */
class CreateRecurringPaymentsProfileResponse extends PaymentDetailResponse {
	/**
	 * A unique identifier for future reference to the details of this recurring payment.
	 * Up to 14 single-byte alphanumeric characters.
	 *
	 * @var string
	 */
	protected $profileId;

	/**
	 * @param string $profileId
	 * @return static
	 */
	public function setProfileId( string $profileId ): self {
		$this->profileId = $profileId;
		return $this;
	}
}
