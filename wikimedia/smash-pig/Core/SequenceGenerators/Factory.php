<?php

namespace SmashPig\Core\SequenceGenerators;

use SmashPig\Core\Context;

class Factory {
	/**
	 * @param string $name name of sequence generator as used in config
	 * @return ISequenceGenerator
	 */
	public static function getSequenceGenerator( $name ) {
		$ctx = Context::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		return $globalConfig->object( "sequence-generator/$name" );
	}
}
