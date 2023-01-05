<?php namespace PayWithAmazon\Mocks;

abstract class MockBaseClient {

	// This directory should contain JSON files with mock responses.
	protected $responseDirectory;

	// Each key is a method name whose value is an array of times it's been
	// called, recording all argument values.
	public $calls = array();

	// Keys are method names, values are arrays of error codes such as InvalidPaymentMethod
	// When a code is not found, the operation will return a successful result
	public $returns = array();

	// Similar to $returns, but any entries here are thrown as exceptions
	public $exceptions = array();

	public function __construct( $config = array() ) {
		$this->responseDirectory = $config['response-directory'];
	}

	protected function fakeCall( $functionName, $arguments ) {
		$this->calls[$functionName][] = $arguments;
		$status = null;
		$returnIndex = count( $this->calls[$functionName] ) - 1;
		if ( isset( $this->returns[$functionName] ) && isset( $this->returns[$functionName][$returnIndex] ) ) {
			$status = $this->returns[$functionName][$returnIndex];
		}
		if ( isset( $this->exceptions[$functionName] ) && isset( $this->exceptions[$functionName][$returnIndex] ) ) {
			throw $this->exceptions[$functionName][$returnIndex];
		}
		return MockResponseParser::create( $this->responseDirectory, $functionName, $status );
	}

	public function __get( $name ) {

	}

	public function getParameters() {

	}

	public function setClientId( $value ) {

	}

	public function setMwsServiceUrl( $url ) {

	}

	public function setProxy( $proxy ) {

	}

	public function setSandbox( $value ) {

	}

}
