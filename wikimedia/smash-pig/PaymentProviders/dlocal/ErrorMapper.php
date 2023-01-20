<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\ErrorCode;

class ErrorMapper {
	public static $errorCodes = [
			'300' => ErrorCode::UNKNOWN, // The payment was rejected.
			'301' => ErrorCode::UNKNOWN, // Rejected by bank.
			'302' => ErrorCode::UNKNOWN, // Insufficient amount.
			'303' => ErrorCode::DECLINED_DO_NOT_RETRY, // Card blacklisted.
			'304' => ErrorCode::UNKNOWN, // Score validation.
			'305' => ErrorCode::DECLINED_DO_NOT_RETRY, // Max attempts reached.
			'306' => ErrorCode::UNKNOWN, // Call bank for authorize.
			'307' => ErrorCode::DUPLICATE_ORDER_ID,	// Duplicated payment.
			'308' => ErrorCode::DECLINED_DO_NOT_RETRY, // Credit card disabled.
			'309' => ErrorCode::UNKNOWN, // Card expired.
			'310' => ErrorCode::DECLINED_DO_NOT_RETRY, // Card reported lost.
			'311' => ErrorCode::DECLINED_DO_NOT_RETRY,	// Card requested by the bank.
			'312' => ErrorCode::DECLINED_DO_NOT_RETRY,	// Card restricted by the bank.
			'313' => ErrorCode::DECLINED_DO_NOT_RETRY, // Card reported stolen.
			'314' => ErrorCode::UNKNOWN, // Invalid card number.
			'315' => ErrorCode::UNKNOWN, // Invalid security code
			'316' => ErrorCode::UNKNOWN, // Unsupported operation.
			'317' => ErrorCode::DECLINED_DO_NOT_RETRY, // Rejected due to high risk.
			'318' => ErrorCode::UNKNOWN, // Invalid transaction.
			'319' => ErrorCode::UNKNOWN, // Amount exceeded.
			'320' => ErrorCode::UNKNOWN, // 3D-Secure is required.
			'321' => ErrorCode::UNKNOWN, // Error in Acquirer
	];
}
