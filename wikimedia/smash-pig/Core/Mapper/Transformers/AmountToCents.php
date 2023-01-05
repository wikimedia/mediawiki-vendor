<?php

namespace SmashPig\Core\Mapper\Transformers;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

/**
 * Transformer AmountToCents
 *
 * Amounts are usually passed as an integer, and usually x100 rather than
 * using the currency's true fractional denomination ("cents").  Currencies
 * without a fractional unit are still multiplied, so we have to floor to
 * avoid killing the payment processor.
 *
 * For example: JPY 1000.05 would be changed to 100005, but should be 100000.
 *
 * @package SmashPig\Core\Mapper\Transformers
 */
class AmountToCents extends AbstractTransformer {

	/**
	 * Confirm we have the required 'amount' and 'currency' fields.
	 *
	 * @param array $original
	 *
	 * @return bool
	 */
	public function canItBeTransformed( $original ) {
		return (
			!empty( $original['amount'] )
			&& !empty( $original['currency'] )
			&& is_numeric( $original['amount'] )
		);
	}

	/**
	 * @param array $original
	 * @param array &$transformed
	 *
	 * @return void
	 */
	public function transform( $original, &$transformed ) {
		$amount = CurrencyRoundingHelper::round( $original['amount'], $original['currency'] );
		$transformed['amount'] = $amount * 100;
	}
}
