<?php

namespace SmashPig\PaymentData;

/**
 * Constants to indicate types of errors from payment gateways.
 * Reflects the failure patterns of gateways we have integrated with.
 * TO CONSIDER: should these constants just be properties of PaymentError?
 */
class ErrorCode {
	/** @var int API authentication failure */
	const BAD_SIGNATURE = 1000000;
	/** @var int Normal decline code. FIXME this isn't really an error, is it? */
	const DECLINED = 1000001;
	/** @var int Card declined on suspected villainy - DO NOT RETRY! */
	const DECLINED_DO_NOT_RETRY = 1000002;
	/** @var int We screwed up and reused our identifier. Can increment and retry. */
	const DUPLICATE_ORDER_ID = 1000003;
	/** @var int We expect a txn ID in the processor response, but it's not there! */
	const MISSING_TRANSACTION_ID = 1000004;
	/** @var int Something else essential is missing */
	const MISSING_REQUIRED_DATA = 1000005;
	/** @var int The whole dang response is missing */
	const NO_RESPONSE = 1000006;
	/** @var int Their server tells us that it has timed out */
	const SERVER_TIMEOUT = 1000007;
	/** @var int A supposedly well-defined field has a value we don't know what to do with */
	const UNEXPECTED_VALUE = 1000008;
	/** @var int For use in default: cases. When encountered, classify the error and add here if needed */
	const UNKNOWN = 1000009;
	/** @var int A supposedly well-defined field has a value that is not supported */
	const ACCOUNT_MISCONFIGURATION = 1000010;
	/** @var int Their server has an internal error */
	const INTERNAL_ERROR = 1000011;
	/** @var int Payment method not found for supplied token */
	const METHOD_NOT_FOUND = 1000012;
	/** @var int Too many requests made to the server per time */
	const EXCEEDED_LIMIT = 1000013;
	/** @var int Validation error on a field */
	const VALIDATION = 1000014;
	/** @var int Caller sent in a transaction ID that does not exist in processor's system */
	const TRANSACTION_NOT_FOUND = 1000015;
	/** @var int Invalid subscription status for cancel action; should be active or suspended */
	const SUBSCRIPTION_CANNOT_BE_CANCELED = 1000016;
	/** @var int Invalid request */
	const INVALID_REQUEST = 1000017;
}
