<?php

namespace SmashPig\PaymentProviders\dlocal;

class Api {

	/**
	 * @var string
	 */
	protected $endpoint;

	/**
	 * @var string
	 */
	protected $login;

	/**
	 * @var string
	 */
	protected $trans_key;

	/**
	 * @var string
	 */
	protected $secret;

	public function __construct( array $params ) {
		$this->endpoint = $params['endpoint'];
		$this->login = $params['login'];
		$this->trans_key = $params['trans_key'];
		$this->secret = $params['secret'];
	}

	protected function makeApiCall() {
		// TODO: Implement makeApiCall() method.
	}
}
