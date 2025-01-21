<?php

namespace SmashPig\Core\Http;

/**
 * Validates HTTP responses based on a limited set of acceptable responses
 */
class EnumValidator implements ResponseValidator {

	/**
	 * @var array
	 */
	protected $validValues;

	/**
	 * EnumValidator constructor.
	 * @param array $validValues
	 */
	public function __construct( $validValues ) {
		$this->validValues = $validValues;
	}

	/**
	 * @param array $parsedResponse with keys 'status', 'headers', and 'body'
	 * @return bool Whether to retry the request
	 */
	public function shouldRetry( array $parsedResponse ): bool {
		return !in_array( $parsedResponse['body'], $this->validValues );
	}
}
