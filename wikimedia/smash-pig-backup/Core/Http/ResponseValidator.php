<?php

namespace SmashPig\Core\Http;

interface ResponseValidator {
	/**
	 * @param array $parsedResponse with keys 'status', 'headers', and 'body'
	 * @return bool Whether to retry the request
	 */
	public function shouldRetry( array $parsedResponse ): bool;
}
