<?php
namespace PayWithAmazon;

require_once 'BaseClient.php';
require_once 'ReportsClientInterface.php';

class ReportsClient extends BaseClient implements ReportsClientInterface {

    // Report types
    const OFFAMAZONPAYMENTS_AUTHORIZATION = '_GET_FLAT_FILE_OFFAMAZONPAYMENTS_AUTHORIZATION_DATA_';
    const OFFAMAZONPAYMENTS_CAPTURE = '_GET_FLAT_FILE_OFFAMAZONPAYMENTS_CAPTURE_DATA_';
    const OFFAMAZONPAYMENTS_ORDER_REFERENCE = '_GET_FLAT_FILE_OFFAMAZONPAYMENTS_ORDER_REFERENCE_DATA_';
    const OFFAMAZONPAYMENTS_REFUND = '_GET_FLAT_FILE_OFFAMAZONPAYMENTS_REFUND_DATA_';
    const OFFAMAZONPAYMENTS_SETTLEMENT = '_GET_FLAT_FILE_OFFAMAZONPAYMENTS_SETTLEMENT_DATA_';

    protected $serviceVersion = '2009-01-01';
    // When throttled, wait a full minute for quota to refill
    protected $basePause = 60000000;

    // Overriding to support ReportTypeList and ReportRequestIdList
    protected $listPrefixes = array(
        'ReportRequestIdList' => 'ReportRequestIdList.Id',
        'ReportTypeList' => 'ReportTypeList.Type',
    );

    protected function setModePath() {
        $this->modePath = 'Reports';
    }

    /* GetReportList API call - Returns a list of reports that were created in the previous 90 days.
     * @see http://docs.developer.amazonservices.com/en_US/reports/Reports_GetReportList.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @optional requestParameters['acknowledged'] - [Boolean]
     * @optional requestParameters['available_from_date'] - [String] ISO8601
     * @optional requestParameters['available_to_date'] - [String] ISO8601
     * @optional requestParameters['max_count'] - [Integer] 1-100, default 10
     * @optional requestParameters['report_request_id_list'] - [Array] of integers
     * @optional requestParameters['report_type_list'] - [Array] of type constants defined above
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getReportList($requestParameters = array())
    {
        $parameters['Action'] = 'GetReportList';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id'			=> 'SellerId',
            'acknowledged'			=> 'Acknowledged',
            'available_from_date'	=> 'AvailableFromDate',
            'available_to_date'		=> 'AvailableToDate',
            'max_count'				=> 'MaxCount',
            'report_request_id_list' => 'ReportRequestIdList',
            'report_type_list'		=> 'ReportTypeList',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);
        return ($responseObject);
    }

    /* GetReport API call - Returns the contents of a report and the Content-MD5 header for the returned report body.
     * @see http://docs.developer.amazonservices.com/en_US/reports/Reports_GetReport.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['report_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getReport($requestParameters = array())
    {

        $parameters['Action'] = 'GetReport';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'report_id' => 'ReportId',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters, false);
        return ($responseObject);
    }
}
