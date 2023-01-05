<?php

namespace SmashPig\PaymentProviders\Braintree;

class TransactionType {
	const AUTHORIZE = 'authorize';
	const CAPTURE = 'capture';
	const CHARGE = 'charge';
}
