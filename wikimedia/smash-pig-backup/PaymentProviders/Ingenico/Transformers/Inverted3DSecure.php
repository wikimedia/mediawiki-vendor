<?php
namespace SmashPig\PaymentProviders\Ingenico\Transformers;

use SmashPig\Core\Mapper\Transformers\AbstractTransformer;

class Inverted3DSecure extends AbstractTransformer {

	public function transform( $original, &$transformed ) {
		// FIXME: bare boolean true is converted to 1 in Mapper
		$transformed['skip_3d_secure'] = ( $original['use_3d_secure'] ?? false ) ? false : 'true';
	}
}
