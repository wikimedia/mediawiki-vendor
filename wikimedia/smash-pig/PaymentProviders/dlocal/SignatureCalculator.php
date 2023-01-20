<?php

namespace SmashPig\PaymentProviders\dlocal;

class SignatureCalculator {
	public const ALGORITHM = 'sha256';

	/**
	 * Generate a 'V2-HMAC-SHA256' signature
	 * https://docs.dlocal.com/reference/payins-security#headers
	 *
	 * @param string $message
	 * @param string $secret
	 *
	 * @return string
	 */
	public function calculate( string $message, string $secret ) : string {
		return hash_hmac( self::ALGORITHM, $message, $secret );
	}

}
