<?php namespace SmashPig\Core\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response extends SymfonyResponse {
	protected $outputDisabled = false;

	public function send() {
		if ( !$this->outputDisabled ) {
			return parent::send();
		} else {
			return $this;
		}
	}

	public function setOutputDisabled( $disabled = true ) {
		$this->outputDisabled = $disabled;
	}

	public function getOutputDisabled() {
		return $this->outputDisabled;
	}
}
