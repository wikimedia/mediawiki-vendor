<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;

class RefundPaymentStatusNormalizer extends PaymentStatusNormalizer {
	protected $successStatus = [ FinalStatus::PENDING, FinalStatus::COMPLETE ];
}
