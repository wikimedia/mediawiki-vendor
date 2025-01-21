<?php namespace PayWithAmazon\Mocks;

use PayWithAmazon\ReportsClientInterface;

/**
 * Stubs out the functionality of the ReportsClient class from the Login and
 * Pay with Amazon SDK.
 */
class MockReportsClient extends MockBaseClient implements ReportsClientInterface {

	public function getReport( $requestParameters = array() ) {
		return $this->fakeCall( 'getReport', $requestParameters );
	}

	public function getReportList( $requestParameters = array() ) {
		return $this->fakeCall( 'getReportList', $requestParameters );
	}
}
