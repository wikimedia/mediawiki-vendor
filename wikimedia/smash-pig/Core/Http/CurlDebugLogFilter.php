<?php

namespace SmashPig\Core\Http;

/**
 * This class is our custom stream_filter implementation that we use
 * to filter out unwanted log lines from the curl verbose logging in
 * the CurlWrapper::execute() call.
 *
 * Class CurlDebugLogFilter
 * @package SmashPig\Core\Http
 */
class CurlDebugLogFilter extends \php_user_filter {
	public function filter( $in, $out, &$consumed, $closing ): int {
		while ( $bucket = stream_bucket_make_writeable( $in ) ) {
			$bucket->data = $this->filterExpireLogLines( $bucket->data ) ?: $bucket->data;
			if ( $bucket->data ) {
				foreach ( preg_split( '/\\R+/', $bucket->data ) as $line ) {
					call_user_func( $this->params, $line );
				}
			}
			$consumed += $bucket->datalen;
			stream_bucket_append( $out, $bucket );
		}
		return PSFS_PASS_ON;
	}

	/**
	 * Filter out '* Expire in 1 ms for 1 (transfer 0x561b6ead0240)' log lines
	 * @param string $data
	 * @return string|null
	 */
	private function filterExpireLogLines( string $data ): ?string {
		return preg_replace( '/\* Expire in.*\n?/', '', $data );
	}

}
