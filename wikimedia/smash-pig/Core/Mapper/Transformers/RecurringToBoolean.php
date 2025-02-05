<?php

namespace SmashPig\Core\Mapper\Transformers;

/**
 * Transformer RecurringToBoolean
 *
 * Recurring may be passed as 1/0 or the strings 'true' or 'false'.
 * Some providers may need it to be passed as a strict boolean
 *
 * @package SmashPig\Core\Mapper\Transformers
 */
class RecurringToBoolean extends AbstractTransformer {

	/**
	 * Only act if some value has been passed for 'recurring'
	 *
	 * @param array $original
	 *
	 * @return bool
	 */
	public function canItBeTransformed( $original ) {
		return isset( $original['recurring'] );
	}

	/**
	 * @param array $original
	 * @param array $transformed
	 *
	 * @return void
	 */
	public function transform( $original, &$transformed ) {
		$trueValues = [ 1, '1', true, 'true' ];
		$transformed['recurring'] = in_array( $original['recurring'], $trueValues, true );
	}
}
