<?php

namespace SmashPig\Core;

class ApiException extends SmashPigException {

	protected $rawErrors;

	public function setRawErrors( $errors ) {
		$this->rawErrors = $errors;
	}

	public function getRawErrors() {
		return $this->rawErrors;
	}

	public function setMessage( $message ) {
		$this->message = $message;
	}

}
