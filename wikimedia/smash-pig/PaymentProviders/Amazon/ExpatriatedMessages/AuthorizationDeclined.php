<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class AuthorizationDeclined extends PaymentAuthorization {

	/**
	 * @var string should be one of the constants in ReasonCode
	 */
	protected $reasonCode;

	public function __construct( $values ) {
		parent::__construct( $values );
		$details = $values['AuthorizationDetails'];
		$this->reasonCode = $details['AuthorizationStatus']['ReasonCode'];
	}

	public function getReasonCode() {
		return $this->reasonCode;
	}
}
