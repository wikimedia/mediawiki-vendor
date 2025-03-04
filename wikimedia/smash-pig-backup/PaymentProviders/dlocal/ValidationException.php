<?php

namespace SmashPig\PaymentProviders\dlocal;

class ValidationException extends \Exception {
	protected $data = [];

	/**
	 * @param string $message
	 * @param array data
	 */
	public function __construct( $message = "", $data = [] ) {
		parent::__construct( $message );
		$this->data = $data;
	}

	/**
	 * @return array
	 */
	public function getData(): array {
		return $this->data;
	}
}
