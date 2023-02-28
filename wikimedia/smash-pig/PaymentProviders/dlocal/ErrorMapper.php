<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\ErrorCode;

class ErrorMapper {
	// Payment status codes for erroneous payments
	// Source: https://docs.dlocal.com/reference/http-errors-payments#errors
	public static $paymentStatusErrorCodes = [
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

	// Error response code mapping
	// Source: https://docs.dlocal.com/reference/http-errors-payments#http-errors
	public static $errorCodes = [
		'3001' => ErrorCode::ACCOUNT_MISCONFIGURATION, // Invalid Credentials.
		'3002' => ErrorCode::UNKNOWN, // Unregistered IP address.
		'3003' => ErrorCode::BAD_SIGNATURE, // Merchant has no authorization to use this API.
		'4000' => ErrorCode::TRANSACTION_NOT_FOUND, // Payment not found.
		'5000' => ErrorCode::INVALID_REQUEST, // Invalid request.
		'5001' => ErrorCode::MISSING_REQUIRED_DATA, // Missing parameter. [parameter_name]
		'5002' => ErrorCode::UNKNOWN, // Invalid transaction status.
		'5003' => ErrorCode::VALIDATION,	// Country not supported.
		'5004' => ErrorCode::VALIDATION, // Currency not allowed for this country.
		'5005' => ErrorCode::UNKNOWN, // User unauthorized due to cadastral situation.
		'5006' => ErrorCode::EXCEEDED_LIMIT, // User limit exceeded.
		'5007' => ErrorCode::VALIDATION,	// Amount exceeded.
		'5008' => ErrorCode::VALIDATION,	// Token not found or inactive.
		'5009' => ErrorCode::DUPLICATE_ORDER_ID, // Order ID is duplicated.
		'5010' => ErrorCode::METHOD_NOT_FOUND, // Method not available.
		'5013' => ErrorCode::ACCOUNT_MISCONFIGURATION, // Unsupported operation.
		'5014' => ErrorCode::DECLINED_DO_NOT_RETRY, // User blacklisted.
		'5016' => ErrorCode::UNEXPECTED_VALUE, // Amount too low.
		'5017' => ErrorCode::ACCOUNT_MISCONFIGURATION, // Invalid API Version.
		'5018' => ErrorCode::UNKNOWN, // Chargeback in place for this transaction.
		'5021' => ErrorCode::INTERNAL_ERROR, // Acquirer could not process the request.
		'6000' => ErrorCode::EXCEEDED_LIMIT, // Too many requests to the API.
		'7000' => ErrorCode::INTERNAL_ERROR, // Failed to process the request.
//		'5010' => ErrorCode::UNKNOWN, // Request Timeout. => duplicate
	];
}
