<?php
namespace SmashPig\Core;

/**
 * FIXME: needs some refactoring. See https://phabricator.wikimedia.org/T294957
 * Represents a validation error associated with a field or the 'general' bucket.
 */
class ValidationError {

	/**
	 * FIXME: add a set of constants for field names
	 * @var string normalized name of field with the error
	 */
	protected $field;

	/**
	 * FIXME: i18n stuff is outside of SmashPig's domain. Replace with a field to hold
	 * a constant indicating which type of error this is
	 * @var string|null A key indicating which message should be displayed to the user
	 */
	protected $messageKey;

	/**
	 * FIXME: i18n stuff is outside of SmashPig's domain. If we do want to return
	 * parameters to the calling code, use a context object with named keys like 'minimum'
	 * @var array any parameters that should be interpolated into the displayed message
	 */
	protected $messageParams;

	/**
	 * @var string|null message from gateway stating error detail
	 */
	protected $debugMessage;

	/**
	 * ValidationError constructor.
	 * @param string $field normalized field name
	 * @param string|null $messageKey i18n key for the error message
	 * @param string|null $debugMessage message from gateway stating error detail
	 * @param array|null $messageParams parameters to interpolate into the message
	 */
	public function __construct( string $field, ?string $messageKey = null, array $messageParams = [], ?string $debugMessage = null ) {
		$this->field = $field;
		$this->messageKey = $messageKey;
		$this->messageParams = $messageParams;
		$this->debugMessage = $debugMessage;
	}

	public function getField(): string {
		return $this->field;
	}

	public function getMessageKey(): ?string {
		return $this->messageKey;
	}

	public function setMessageKey( ?string $key ) {
		$this->messageKey = $key;
	}

	public function getDebugMessage(): ?string {
		return $this->debugMessage;
	}

	public function getMessageParams(): array {
		return $this->messageParams;
	}
}
