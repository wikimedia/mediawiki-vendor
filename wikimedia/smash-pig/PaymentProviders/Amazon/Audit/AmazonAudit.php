<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

use SmashPig\Core\Context;
use SmashPig\Core\DataFiles\AuditParser;

/**
 * Parses off-Amazon payments reports retrieved from MWS
 */
class AmazonAudit implements AuditParser {

	public function parseFile( string $path ): array {
		$config = Context::get()->getProviderConfiguration();
		$fileTypes = $config->val( 'audit/file-types' );

		$data = [];

		foreach ( $fileTypes as $type ) {
			if ( $type::isMine( $path ) ) {
				$parser = new $type();
				$data = $parser->parse( $path );
			}
		}

		return $data;
	}
}
