<?php

namespace SmashPig\PaymentProviders\Ingenico\Transformers;

use SmashPig\Core\Mapper\Transformers\RecurringToBoolean;
use SmashPig\PaymentData\RecurringModel;

class IngenicoRecurring extends RecurringToBoolean {

	public function transform( $original, &$transformed ) {
		parent::transform( $original, $transformed );
		$recurringModel = $original['recurring_model'] ?? RecurringModel::SUBSCRIPTION;
		if ( $transformed['recurring'] ) {
			if ( $recurringModel === RecurringModel::SUBSCRIPTION ) {
				// Donor has indicated they definitely want to start a monthly recurring donations
				$transformed['recurring_payment_sequence_indicator'] = 'first';
				// FIXME bare boolean gets converted to 1 in mapper
				$transformed['recurring'] = 'true';
				$transformed['tokenize'] = 'true';
			} else {
				// Speculative tokenization, saving the card in case the donor agrees to add a
				// monthly contribution later.
				$transformed['tokenize'] = 'true';
				$transformed['recurring'] = false;
			}
		} else {
			$transformed['recurring'] = false;
		}
	}
}
