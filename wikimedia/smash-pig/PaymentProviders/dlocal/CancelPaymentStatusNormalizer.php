<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;

class CancelPaymentStatusNormalizer extends PaymentStatusNormalizer {
	protected $successStatus = [ FinalStatus::CANCELLED ];
}
