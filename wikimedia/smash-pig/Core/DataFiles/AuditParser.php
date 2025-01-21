<?php

namespace SmashPig\Core\DataFiles;

interface AuditParser {

	/**
	 * Parse an audit file and normalize records
	 *
	 * @param string $path Full path of the file to be parsed
	 * @return array of donation, refund, and chargeback records
	 */
	public function parseFile( string $path ): array;
}
