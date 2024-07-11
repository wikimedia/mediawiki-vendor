<?php

namespace SmashPig\PaymentProviders\Responses;

use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;

/**
 * Class PaymentProviderResponse
 * @package SmashPig\PaymentProviders
 *
 * Skeleton of a standard Payment Provider API response.
 */
abstract class PaymentProviderResponse {

	/**
	 * array of errors returned
	 * @var PaymentError[]
	 */
	protected $errors = [];

	/**
	 * FIXME: should find a cleaner way to fold these in with the PaymentErrors above
	 * @var ValidationError[]
	 */
	protected $validationErrors = [];

	/**
	 * raw response sent back from payment provider
	 * @var mixed
	 */
	protected $rawResponse;

	/**
	 * normalized response from provider response
	 * @var mixed
	 */
	protected $normalizedResponse;

	/**
	 * Payment provider transaction ID
	 *
	 * https://www.mediawiki.org/wiki/Fundraising_tech/Transaction_IDs
	 * Also note the spelling: gateway_txn_id has no 'r' in txn. This is to maintain
	 * consistency with our queue messages and wmf_contribution_extra.gateway_txn_id
	 * column. Maybe one day we'll add the R.
	 *
	 * @var string|null
	 */
	protected $gateway_txn_id;

	/**
	 * mapped PaymentStatus status for the providers transaction status
	 * @var string|null
	 */
	protected $status;

	/**
	 * raw provider status in its original form.
	 * @var string|null
	 */
	protected $rawStatus;

	/**
	 * @var bool
	 */
	protected $successful;

	/**
	 * Time taken in milliseconds
	 * @var int
	 */
	protected $timeTaken;

	/**
	 * @return mixed
	 */
	public function getRawResponse() {
		return $this->rawResponse;
	}

	/**
	 * @return mixed
	 */
	public function getNormalizedResponse() {
		return $this->normalizedResponse;
	}

	/**
	 * @param mixed $rawResponse
	 * @return $this
	 */
	public function setRawResponse( $rawResponse ): self {
		$this->rawResponse = $rawResponse;
		return $this;
	}

	/**
	 * @param mixed $normalizedResponse
	 * @return $this
	 */
	public function setNormalizedResponse( $normalizedResponse ): self {
		$this->normalizedResponse = $normalizedResponse;
		return $this;
	}

	/**
	 * @return PaymentError[]
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * @return ValidationError[]
	 */
	public function getValidationErrors() {
		return $this->validationErrors;
	}

	/**
	 * @param PaymentError[] $errors
	 * @return $this
	 */
	public function setErrors( $errors ): self {
		$this->errors = $errors;
		return $this;
	}

	/**
	 * Returns true if EITHER the PaymentError array (see getErrors) OR
	 * the ValidationError array (see getValidationErrors) has elements.
	 *
	 * @return bool
	 */
	public function hasErrors(): bool {
		return count( $this->getErrors() ) > 0 || count( $this->getValidationErrors() ) > 0;
	}

	/**
	 * Convenience function to check for a specific error code in the PaymentError stack
	 *
	 * @param string $errorCode one of the ErrorCode constants
	 * @return bool
	 */
	public function hasError( string $errorCode ): bool {
		foreach ( $this->getErrors() as $error ) {
			if ( $error->getErrorCode() === $errorCode ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add error(s) to the stack
	 *
	 * @param PaymentError[]|PaymentError $errors
	 * @return $this
	 */
	public function addErrors( $errors ): self {
		if ( !is_array( $errors ) ) {
			$errors = [ $errors ];
		}
		foreach ( $errors as $error ) {
			if ( !$this->hasError( $error->getErrorCode() ) ) {
				array_push( $this->errors, $error );
			}
		}
		return $this;
	}

	/**
	 * Adds a validation error to the stack
	 * @param ValidationError $error
	 * @return $this
	 */
	public function addValidationError( ValidationError $error ): self {
		array_push( $this->validationErrors, $error );
		return $this;
	}

	/**
	 * @return string
	 */
	public function getGatewayTxnId(): ?string {
		return $this->gateway_txn_id;
	}

	/**
	 * @param string $gateway_txn_id
	 * @return static
	 */
	public function setGatewayTxnId( string $gateway_txn_id ): self {
		$this->gateway_txn_id = $gateway_txn_id;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getStatus(): ?string {
		return $this->status;
	}

	/**
	 * @param string $status
	 * @return static
	 */
	public function setStatus( string $status ): self {
		$this->status = $status;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isSuccessful(): bool {
		return $this->successful;
	}

	/**
	 * @param bool $successful
	 * @return $this
	 */
	public function setSuccessful( bool $successful ): self {
		$this->successful = $successful;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getRawStatus(): ?string {
		return $this->rawStatus;
	}

	/**
	 * @param string $rawStatus
	 * @return static
	 */
	public function setRawStatus( string $rawStatus ): self {
		$this->rawStatus = $rawStatus;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTimeTaken(): int {
		return $this->timeTaken;
	}

	/**
	 * @param int $timeTaken
	 * @return static
	 */
	public function setTimeTaken( int $timeTaken ): self {
		$this->timeTaken = $timeTaken;
		return $this;
	}
}
