<?php namespace PayWithAmazon\Mocks;

use BadMethodCallException;
use PayWithAmazon\ResponseInterface;

/**
 * Stubs out the functionality of the ResponseParser from the Login and Pay with
 * Amazon SDK.
 */
class MockResponseParser implements ResponseInterface {

	protected $response;

	public function __construct( $response = null ) {
		$this->response = $response;
	}

	/**
	 * Creates the fake response from JSON
	 * @param string $responseDirectory Directory holding the JSON files
	 * @param string $operation The PaymentsClient function call we're faking
	 * @param string $status Set to fake responses with an error status
	 *		Reads from $operation_$status.json
	 */
	public static function create( $responseDirectory, $operation, $status = null ) {
		$statusPart = $status ? '_' . $status : '';
		$filePath = "$responseDirectory/{$operation}{$statusPart}.json";
		$json = file_get_contents( $filePath );
		return new MockResponseParser( json_decode( $json, true ) );
	}

	public function getBillingAgreementDetailsStatus( $response ) {
		throw new BadMethodCallException( 'Not implemented' );
	}

	public function toArray() {
		return $this->response;
	}

	public function toJson() {
		return json_encode( $this->response );
	}

	public function toXml() {
		throw new BadMethodCallException( 'Not implemented' );
	}

}
