<?php

namespace SmashPig\Core\Mapper\Transformers;

use SmashPig\Core\Context;

/**
 * Transformer DataConstraints
 *
 * Truncates parameters
 */
class DataConstraints extends AbstractTransformer {

	/**
	 * We can try truncating no matter what keys exist
	 *
	 * @param array $original
	 *
	 * @return bool
	 */
	public function canItBeTransformed( $original ) {
		return true;
	}

	/**
	 * @param array $original
	 * @param array $transformed
	 *
	 * @return void
	 */
	public function transform( $original, &$transformed ) {
		$constraints = Context::get()->getProviderConfiguration()->val( 'data-constraints' );
		if ( empty( $constraints ) ) {
			return;
		}
		foreach ( $transformed as $key => $value ) {
			if ( !empty( $constraints[$key] ) && !empty( $constraints[$key]['length'] ) ) {
				$maxLength = $constraints[$key]['length'];
				if ( is_string( $value ) && mb_strlen( $value ) > $maxLength ) {
					$transformed[$key] = mb_substr( $transformed[$key], 0, $maxLength );
				}
			}
		}
	}
}
