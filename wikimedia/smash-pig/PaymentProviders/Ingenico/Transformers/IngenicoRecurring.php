<?php

namespace SmashPig\PaymentProviders\Ingenico\Transformers;

use SmashPig\Core\Mapper\Transformers\RecurringToBoolean;

class IngenicoRecurring extends RecurringToBoolean {

	public function transform( $original, &$transformed ) {
		parent::transform( $original, $transformed );
		if ( $transformed['recurring'] ) {
			$transformed['recurring_payment_sequence_indicator'] = 'first';
			// FIXME bare boolean gets converted to 1 in mapper
			$transformed['recurring'] = 'true';
		} else {
			$transformed['recurring'] = false;
		}
	}
}
