<?php

namespace WebVTT\Parser;

use Closure;

/**
 * Parser for generic key/value options.
 */
readonly class OptionsParser {

	/**
	 * Helper function to parse input into groups separated by 'groupDelim', and
	 * interpret each group as a key/value pair separated by 'keyValueDelim'.
	 *
	 * @param string $input
	 * @param Closure $callback
	 * @param string $keyValueDelim
	 * @param string|null $groupDelim A regexp string
	 * @return void
	 */
	public function parse( string $input, Closure $callback, string $keyValueDelim, ?string $groupDelim = null ): void {
		$groups = $groupDelim ? preg_split( $groupDelim, $input ) : [ $input ];
		foreach ( $groups as $i => $group ) {
			$kv = explode( $keyValueDelim, $group, 2 );
			if ( count( $kv ) !== 2 ) {
				continue;
			}
			$k = $kv[0];
			$v = $kv[1];
			$callback( $k, $v );
		}
	}
}
