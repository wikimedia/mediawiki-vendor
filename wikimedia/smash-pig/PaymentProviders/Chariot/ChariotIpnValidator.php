<?php namespace SmashPig\PaymentProviders\Chariot;

class ChariotIpnValidator {

	/**
	 * @param array $postFields Associative array of fields posted to listener
	 * @return bool
	 */
	public function validate( $postFields = [] ): bool {
		return true;
	}

}
