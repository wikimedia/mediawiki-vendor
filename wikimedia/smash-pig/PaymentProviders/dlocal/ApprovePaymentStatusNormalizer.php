<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;

class ApprovePaymentStatusNormalizer extends PaymentStatusNormalizer {
	protected $successStatus = [ FinalStatus::COMPLETE ];
}
