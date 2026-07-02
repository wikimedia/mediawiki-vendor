<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

use Psr\Http\Message\UriInterface;

/**
 * Encapsulation of an output file which is sent to a server using a PUT
 * request.
 *
 * @since 4.1.0
 */
class OutputFileToUrl extends OutputFile {
	private array $headers = [];
	private bool $mwContentHash = false;

	/**
	 * @internal
	 */
	public function __construct( private readonly UriInterface|string $url ) {
	}

	/**
	 * Replace the current array of headers with the specified list. The array
	 * should be in Guzzle's format: an associative array where the key is the
	 * header name and the value is either a string or an array of strings.
	 * Array values are used to send multiple headers with the same name.
	 *
	 * @param array $headers
	 * @return $this
	 */
	public function headers( array $headers ) {
		$this->headers = $headers;
		return $this;
	}

	/**
	 * Set a flag which, if enabled, will cause etag and x-object-meta-sha1base36
	 * headers to be set from the content of the file, according to Swift and
	 * MediaWiki conventions.
	 *
	 * @param bool $enable
	 * @return $this
	 */
	public function enableMwContentHash( bool $enable = true ) {
		$this->mwContentHash = $enable;
		return $this;
	}

	public function getClientData() {
		return [
			'type' => 'url',
			'url' => (string)$this->url,
			'headers' => $this->headers,
			'mwContentHash' => $this->mwContentHash,
		];
	}

	/**
	 * Get the URL
	 *
	 * @internal
	 * @return UriInterface|string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * Get the headers
	 *
	 * @internal
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Determine whether MW content hashing is enabled
	 *
	 * @internal
	 * @return bool
	 */
	public function isMwContentHashEnabled() {
		return $this->mwContentHash;
	}
}
