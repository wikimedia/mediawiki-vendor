<?php namespace SmashPig\PaymentProviders\Fundraiseup\Audit;

use SmashPig\Core\Context;
use SmashPig\Core\DataFiles\AuditParser;

class FundraiseupAudit implements AuditParser {

	/**
	 * @inheritDoc
	 */
	public function parseFile( string $path ): array {
		$config = Context::get()->getProviderConfiguration();
		$fileTypes = $config->val( 'audit/file-types' );
		$fileData = [];
		foreach ( $fileTypes as $type ) {
			if ( $type::isMatch( $path ) ) {
				$parser = new $type();
				$fileData = $parser->parse( $path );
				break;
			}
		}
		return $fileData;
	}

}
