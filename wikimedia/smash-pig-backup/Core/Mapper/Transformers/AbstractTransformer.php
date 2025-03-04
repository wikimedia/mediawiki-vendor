<?php

namespace SmashPig\Core\Mapper\Transformers;

/**
 * Transformers allow changes to %placeholder% values and formats
 * during the mapping process. Transformers can be passed as a Closure with two
 * arguments ($original,$transformed) or they can be classes which extend
 * AbstractTransformer.
 *
 * Note: $transformed is passed between all Transformers to allow
 * "layering" of Transformer behaviour. Due to this, within the scope
 * of your transform method (or Closure transformer), always refer to
 * $transformed['field'] for the latest version of that field and only
 * use $original['field'] when you want to know the original state prior to any
 * Transformations being applied.
 *
 * @package SmashPig\Core\Mapper\Transformers
 */
abstract class AbstractTransformer implements Transformer {

	/**
	 * @param array $original original input data
	 * @param array $transformed copy of $original passed in by reference to be updated
	 * and passed back as transformed output data.
	 *
	 */
	public function __invoke( $original, &$transformed ) {
		if ( $this->canItBeTransformed( $original ) ) {
			return $this->transform( $original, $transformed );
		}
	}

	/**
	 * @param array $original original input data
	 * @param array $transformed copy of $original passed in by reference to be updated
	 * and passed back as transformed output data.
	 *
	 */
	abstract public function transform( $original, &$transformed );

	/**
	 * Check that the requirements for transformation exist. Put your field
	 * and type checks in here.
	 *
	 * Defaults to true.
	 *
	 * @param array $original original input data
	 *
	 * @return bool
	 */
	public function canItBeTransformed( $original ) {
		return true;
	}
}
