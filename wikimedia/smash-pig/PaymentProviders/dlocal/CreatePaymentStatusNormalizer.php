<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;

class CreatePaymentStatusNormalizer extends PaymentStatusNormalizer {
	protected $successStatus = [
		FinalStatus::COMPLETE,
		FinalStatus::PENDING,
		FinalStatus::PENDING_POKE,
	];
}
