<?php
namespace PayWithAmazon;

/* Interface class to showcase the public API methods for MWS reports */

interface ReportsClientInterface
{
    /* Takes user configuration array from the user as input
     * Takes JSON file path with configuration information as input
     * Validates the user configuation array against existing config array
     */
    
    public function __construct($config = null);
    
    /* Setter for sandbox
     * Sets the boolean value for config['sandbox'] variable
     */
    
    public function setSandbox($value);
    
    /* Setter for config['client_id']
     * Sets the  value for config['client_id'] variable
     */
    
    public function setClientId($value);
    
    /* Setter for Proxy
     * input $proxy [array]
     * @param $proxy['proxy_user_host'] - hostname for the proxy
     * @param $proxy['proxy_user_port'] - hostname for the proxy
     * @param $proxy['proxy_user_name'] - if your proxy required a username
     * @param $proxy['proxy_user_password'] - if your proxy required a passowrd
     */
    
    public function setProxy($proxy);
    
    /* Setter for $_mwsServiceUrl
     * Set the URL to which the post request has to be made for unit testing 
     */
    
    public function setMwsServiceUrl($url);
    
    /* Getter
     * Gets the value for the key if the key exists in config
     */
    
    public function __get($name);
    
    /* Getter for parameters string
     * Gets the value for the parameters string for unit testing
     */
    
    public function getParameters();

    /* GetReportList API call - Returns a list of reports that were created in the previous 90 days.
     * @see http://docs.developer.amazonservices.com/en_US/reports/Reports_GetReportList.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @optional requestParameters['acknowledged'] - [Boolean]
     * @optional requestParameters['available_from_date'] - [String] ISO8601
     * @optional requestParameters['available_to_date'] - [String] ISO8601
     * @optional requestParameters['max_count'] - [Integer] 1-100, default 10
     * @optional requestParameters['report_request_id_list'] - [Array] of integers
     * @optional requestParameters['report_type_list'] - [Array] of strings
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getReportList($requestParameters = array());

	/* GetReport API call - Returns the contents of a report and the Content-MD5 header for the returned report body.
     * @see http://docs.developer.amazonservices.com/en_US/reports/Reports_GetReport.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['report_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getReport($requestParameters = array());
}
