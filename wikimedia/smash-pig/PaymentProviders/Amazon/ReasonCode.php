<?php

namespace SmashPig\PaymentProviders\Amazon;

class ReasonCode {
	const TRANSACTION_TIMED_OUT = 'TransactionTimedOut';
	const INVALID_PAYMENT_METHOD = 'InvalidPaymentMethod';
	const AMAZON_REJECTED = 'AmazonRejected';
	const PROCESSING_FAILURE = 'ProcessingFailure';
	const MAX_CAPTURES_PROCESSED = 'MaxCapturesProcessed';
}
