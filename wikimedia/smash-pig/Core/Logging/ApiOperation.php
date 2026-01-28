<?php

namespace SmashPig\Core\Logging;

/**
 * Canonical API operation names for standardised API timing logs.
 *
 * These enum values ensure consistent naming across different payment processors,
 * making it easier to analyze and compare API performance metrics.
 */
enum ApiOperation: string {
	/**
	 * Create/authorize a payment
	 */
	case AUTHORIZE = 'authorize';

	/**
	 * Capture/approve an authorized payment
	 */
	case CAPTURE = 'capture';

	/**
	 * Refund a payment
	 */
	case REFUND = 'refund';

	/**
	 * Cancel/void a payment
	 */
	case CANCEL = 'cancel';

	/**
	 * Get available payment methods
	 */
	case GET_PAYMENT_METHODS = 'getPaymentMethods';

	/**
	 * Get payment/transaction status
	 */
	case GET_PAYMENT_STATUS = 'getPaymentStatus';

	/**
	 * Get payment details after redirect
	 */
	case GET_PAYMENT_DETAILS = 'getPaymentDetails';

	/**
	 * Create a payment session (e.g., for hosted payment pages)
	 */
	case CREATE_SESSION = 'createSession';

	/**
	 * Delete a stored payment token
	 */
	case DELETE_TOKEN = 'deleteToken';

	/**
	 * Get saved payment details/tokens
	 */
	case GET_SAVED_PAYMENT_DETAILS = 'getSavedPaymentDetails';

	/**
	 * Get refund details
	 */
	case GET_REFUND = 'getRefund';

	/**
	 * Get report execution details
	 */
	case GET_REPORT_EXECUTION = 'getReportExecution';

	/**
	 * Generate report download URL
	 */
	case GET_REPORT_DOWNLOAD_URL = 'getReportDownloadUrl';

	/**
	 * Get payment service definition
	 */
	case GET_PAYMENT_SERVICE_DEFINITION = 'getPaymentServiceDefinition';

	/**
	 * Delete data for GDPR compliance
	 */
	case DELETE_DATA = 'deleteData';

	/**
	 * Verify the provided UPI ID
	 */
	case VERIFY_UPI_ID = 'verifyUpiId';
}
