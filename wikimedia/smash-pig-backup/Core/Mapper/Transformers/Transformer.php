<?php

namespace SmashPig\Core\Mapper\Transformers;

/**
 * Interface Transformer
 *
 * @package SmashPig\Core\Mapper\Transformers
 */
interface Transformer {

	public function __invoke( $original, &$transformed );

	public function canItBeTransformed( $original );

	public function transform( $original, &$transformed );

}
