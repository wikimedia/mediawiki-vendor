<?php

/**
 * Represents a set of error types related to payment processes within the Gravy payment provider.
 * These error types are used to categorize and describe various error conditions.
 *
 * Enumeration cases:
 * - RESPONSE_TYPE: Denotes an error in the response type received.
 * - ERROR_CODE: Indicates the presence of an error code in the response.
 * - FAILED_INTENT: Refers to a failed payment intent.
 * - THREE_D_SECURE: Represents an error related to the 3D Secure authentication process.
 * - FAILED_PAYMENT: Indicates a failure in the payment status.
 */

namespace SmashPig\PaymentProviders\Gravy\Errors;

enum ErrorType: string {
	case RESPONSE_TYPE = 'error_response_type';
	case ERROR_CODE = 'error_code_present';
	case FAILED_INTENT = 'failed_intent';
	case THREE_D_SECURE = '3d_secure_error';
	case FAILED_PAYMENT = 'failed_payment_status';
}
