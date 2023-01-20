<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentData\ErrorCode;

class ExceptionMapper {
	// List from https://developer.paypal.com/api/nvp-soap/errors/
	// todo: could add more later
	protected static $fatalErrorCodes = [
		'11502' => ErrorCode::INVALID_TOKEN,
		'11516' => ErrorCode::MISSING_REQUIRED_DATA, // Invalid billing frequency.
		'11518' => ErrorCode::MISSING_REQUIRED_DATA, // Invalid billing period.
		'11519' => ErrorCode::UNEXPECTED_VALUE, // Invalid amount.
		'11549' => ErrorCode::MISSING_REQUIRED_DATA, // Subscription start date is required.
		'11585' => ErrorCode::UNEXPECTED_VALUE, // Invalid amount.
	];

	/**
	 * @throws ApiException
	 */
	public static function throwOnPaypalError( $paypalResponse ) {
		$exceptionCode = null;
		$exceptionMessage = null;

		if ( $paypalResponse === null ) {
			$exceptionCode = ErrorCode::NO_RESPONSE;
			$exceptionMessage = 'Paypal API call with no valid response';
		} elseif (
			isset( $paypalResponse['L_ERRORCODE0'] ) &&
			isset( self::$fatalErrorCodes[$paypalResponse['L_ERRORCODE0']] )
		) {
			$exceptionCode = self::$fatalErrorCodes[$paypalResponse['L_ERRORCODE0']];
			$exceptionMessage = $paypalResponse['L_LONGMESSAGE0'] ?? 'Error in Paypal API response';
		}
		if ( $exceptionCode !== null ) {
			$exception = new ApiException( $exceptionMessage, $exceptionCode );
			$exception->setRawErrors( [ $paypalResponse ] );
			throw $exception;
		}
	}
}
