<?php

namespace SmashPig\PaymentProviders\Gravy\Errors;

use SmashPig\PaymentData\ErrorCode;

class ErrorMapper {

	/**
	 * @var array
	 * Core failures
	 * Source: https://docs.gr4vy.com/guides/api/resources/transactions/error-codes#core-failures
	 */
	public static $transactionErrorCodesCore = [
		'incomplete_buyer_approval' => ErrorCode::INVALID_REQUEST, // The shipping address is invalid.
		'failed_buyer_approval' => ErrorCode::UNKNOWN, // The service could not create a resource due to a conflict.
		'missing_redirect_url' => ErrorCode::ACCOUNT_MISCONFIGURATION, // The service is configured in an unexpected state.
		'flow_decline' => ErrorCode::UNKNOWN, // An unknown error occurred.
		'all_attempts_skipped	' => ErrorCode::UNKNOWN, // The resource could not be found by the service.
	];

	/**
	 * @var array
	 * Connector declines/Failures
	 * Source: https://docs.gr4vy.com/guides/api/resources/transactions/error-codes#connector-declines
	 */
	public static $transactionErrorCodes = [
			'canceled_payment_method' => ErrorCode::DECLINED_DO_NOT_RETRY, // The payment method reported lost, stolen, or otherwise canceled..
			'disputed_transaction' => ErrorCode::UNKNOWN, // The transaction cannot be refunded due to chargeback.
			'duplicate_transaction' => ErrorCode::DUPLICATE, // The transaction is a duplicate of a previous transaction.
			'expired_authorization' => ErrorCode::UNKNOWN, // The authorization has expired.
			'expired_payment_method' => ErrorCode::UNKNOWN, // The payment method has expired.
			'incorrect_billing_address' => ErrorCode::VALIDATION, // The billing address does not match the account.
			'incorrect_country' => ErrorCode::ACCOUNT_MISCONFIGURATION, // The country code was rejected by the service or issuer.
			'incorrect_currency' => ErrorCode::ACCOUNT_MISCONFIGURATION, // The currency code was rejected by the service or issuer.
			'incorrect_cvv' => ErrorCode::VALIDATION, // The CVV was incorrect.
			'incorrect_expiry_date' => ErrorCode::VALIDATION, // The expiry date is incorrect or the payment method has expired.
			'insufficient_funds' => ErrorCode::UNKNOWN, // The amount exceeds the available balance on the payment method.
			'issuer_decline' => ErrorCode::DECLINED_DO_NOT_RETRY, // The payment was declined by the issuer.
			'other_decline' => ErrorCode::UNKNOWN, // The transaction failed for an unknown reason but may succeed if retried.
			'requires_buyer_authentication' => ErrorCode::VALIDATION, // Additional credentials were requested by the issuer, for example, the security code (CVV).
			'refused_transaction' => ErrorCode::DECLINED_DO_NOT_RETRY, // The transaction was refused due to legal reasons (e.g. watch list, embargo, sanctions).
			'service_decline' => ErrorCode::DECLINED_DO_NOT_RETRY, // The payment was declined by service.
			'suspected_fraud' => ErrorCode::DECLINED_DO_NOT_RETRY, // The service flagged the transaction as suspected fraud.
			'unavailable_payment_method' => ErrorCode::DECLINED_DO_NOT_RETRY, // The payment method is temporarily frozen or otherwise unavailable.
			'unknown_payment_method' => ErrorCode::METHOD_NOT_FOUND, // The account is unknown.
			'unsupported_transaction' => ErrorCode::DECLINED, // The payment method does not support this type of purchase (e.g. gambling is restricted).
			'unsupported_payment_method' => ErrorCode::METHOD_NOT_FOUND, // The payment method is not supported by the service (e.g. card scheme is not supported)
			'insufficient_service_permissions' => ErrorCode::ACCOUNT_MISCONFIGURATION, // The service credentials lack permission to perform the requested action.
			'invalid_amount' => ErrorCode::INVALID_REQUEST,	// The amount not supported by service.
			'invalid_payment_method' => ErrorCode::INVALID_REQUEST, // The payment method is not supported by the service (e.g. card scheme is not supported).
			'invalid_service_configuration' => ErrorCode::INVALID_REQUEST, // The service is incorrectly configured.
			'invalid_service_credentials' => ErrorCode::INVALID_REQUEST, // The service credentials are not valid.
			'invalid_service_response' => ErrorCode::INVALID_REQUEST, // The service response could not be parsed.
			'invalid_tax_identifier' => ErrorCode::INVALID_REQUEST, // The tax identifier is invalid (e.g. GB VAT number is in an invalid format, or is of the wrong kind).
			'missing_billing_address' => ErrorCode::VALIDATION, // The billing address is required.
			'missing_cvv' => ErrorCode::VALIDATION, // The CVV is required.
			'missing_shipping_address' => ErrorCode::VALIDATION, // The shipping address is required.
			'missing_tax_identifier' => ErrorCode::VALIDATION, // The tax identifier is required.
			'refund_period_expired' => ErrorCode::DECLINED_DO_NOT_RETRY, // The refund can not be performed due to the refund period expiring.
			'service_error' => ErrorCode::INTERNAL_ERROR, // The service reported an internal server error or upstream processing error.
			'service_network_error' => ErrorCode::INTERNAL_ERROR, // The service was unreachable or experienced a timeout.
			'service_rate_limit	' => ErrorCode::EXCEEDED_LIMIT, // The service responded with a rate-limiting error.
			'internal_error' => ErrorCode::INTERNAL_ERROR, // An internal error has occurred.
			'invalid_billing_address' => ErrorCode::VALIDATION, // The billing address is invalid.
			'invalid_operation' => ErrorCode::INVALID_REQUEST, // The service/method is not implemented, and operation is not supported for this request.
			'invalid_request_parameters' => ErrorCode::INVALID_REQUEST, // The one or more request parameters are invalid.
			'invalid_service_request' => ErrorCode::INVALID_REQUEST, // The service request could not be parsed.
			'invalid_shipping_address' => ErrorCode::INVALID_REQUEST, // The shipping address is invalid.
			'service_resource_conflict' => ErrorCode::UNKNOWN, // The service could not create a resource due to a conflict.
			'unexpected_state' => ErrorCode::ACCOUNT_MISCONFIGURATION, // The service is configured in an unexpected state.
			'unknown_error' => ErrorCode::UNKNOWN, // An unknown error occurred.
			'unknown_service_resource	' => ErrorCode::UNKNOWN, // The resource could not be found by the service.
			'unsupported_country' => ErrorCode::VALIDATION, // The country is not supported by the service.
			'unsupported_currency' => ErrorCode::VALIDATION, // The currency is not supported by the service.
	];

	/**
	 * @var array
	 * Error response code mapping
	 * source: https://docs.gr4vy.com/reference/errors/server-errors, https://docs.gr4vy.com/reference/errors/client-errors
	 */
	public static $errorCodes = [
		'400' => ErrorCode::VALIDATION, // Bad request.
		'401' => ErrorCode::INVALID_REQUEST, // Unauthorized.
		'403' => ErrorCode::DECLINED, // Forbidden.
		'404' => ErrorCode::TRANSACTION_NOT_FOUND, // Not found.
		'405' => ErrorCode::INVALID_REQUEST, // Method not allowed.
		'409' => ErrorCode::DUPLICATE, // Duplicate record
		'429' => ErrorCode::EXCEEDED_LIMIT, // Too many requests to the API.
		'500' => ErrorCode::INTERNAL_ERROR, // Server Error
		'502' => ErrorCode::INTERNAL_ERROR, // Bad Gateway
		'504' => ErrorCode::SERVER_TIMEOUT, // Gateway timeout
	];

	public static function getError( string $code ) {
		if ( isset( self::$errorCodes[$code] ) ) {
			return self::$errorCodes[$code];
		} elseif ( isset( self::$transactionErrorCodesCore[$code] ) ) {
			return self::$transactionErrorCodesCore[$code];
		} elseif ( isset( self::$transactionErrorCodes[$code] ) ) {
			return self::$transactionErrorCodes[$code];
		}

		return ErrorCode::UNKNOWN;
	}
}
