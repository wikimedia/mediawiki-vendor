<?php

namespace SmashPig\Core\Http;

use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;

/**
 * Uses dependency injection to execute cURL requests
 */
class OutboundRequest {
	/**
	 * @var array
	 */
	protected $headers = [];

	/**
	 * @var ProviderConfiguration
	 */
	protected $config;

	/**
	 * @var string URL
	 */
	protected $url;

	/**
	 * @var string HTTP method
	 */
	protected $method;

	/**
	 * @var string Request body
	 */
	protected $body = null;

	/**
	 * @var string path to SSL certificate to use with the request
	 */
	protected $certPath;

	/**
	 * @var string password to decrypt certificate
	 */
	protected $certPassword;

	public function __construct( $url, $method = 'GET' ) {
		$this->url = $url;
		$this->method = $method;
	}

	public function setHeader( string $name, string $value ): OutboundRequest {
		$this->headers[$name] = $value;
		return $this;
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getUrl(): string {
		return $this->url;
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function setBody( $data ): OutboundRequest {
		if ( is_array( $data ) ) {
			$this->body = http_build_query( $data );
		} else {
			$this->body = $data;
		}
		if ( $this->body === null ) {
			if ( isset( $this->headers['Content-Length'] ) ) {
				unset( $this->headers['Content-Length'] );
			}
		} else {
			$this->setHeader( 'Content-Length', strlen( $this->body ) );
		}
		return $this;
	}

	public function getBody(): string {
		return $this->body;
	}

	/**
	 * @return string
	 */
	public function getCertPath(): string {
		return $this->certPath;
	}

	/**
	 * @param string $certPath
	 * @return OutboundRequest
	 */
	public function setCertPath( string $certPath ): OutboundRequest {
		$this->certPath = $certPath;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCertPassword(): string {
		return $this->certPassword;
	}

	/**
	 * @param string $certPassword
	 * @return OutboundRequest
	 */
	public function setCertPassword( string $certPassword ): OutboundRequest {
		$this->certPassword = $certPassword;
		return $this;
	}

	public function execute(): array {
		$config = Context::get()->getProviderConfiguration();
		/**
		 * @var CurlWrapper
		 */
		$wrapper = $config->object( 'curl/wrapper' );
		return $wrapper->execute(
			$this->url,
			$this->method,
			$this->getHeaders(),
			$this->body,
			$this->certPath,
			$this->certPassword
		);
	}
}
