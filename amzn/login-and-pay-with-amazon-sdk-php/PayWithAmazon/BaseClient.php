<?php
namespace PayWithAmazon;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/* Class BaseClient
 * Takes configuration information
 * Contains common functionality for API calls to MWS
 * returns Response Object
 */

require_once 'ArrayUtil.php';
require_once 'ResponseParser.php';
require_once 'HttpCurl.php';
require_once 'Regions.php';

abstract class BaseClient
{
    const MWS_CLIENT_VERSION = '1.0.0';
    const MAX_ERROR_RETRY = 3;

    // Override in concrete classes with API's service version
    protected $serviceVersion;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    // Construct User agent string based off of the application_name, application_version, PHP platform
    protected $userAgent = null;
    protected $parameters = null;
    protected $mwsEndpointPath = null;
    protected $mwsEndpointUrl = null;
    protected $config = array('merchant_id' 	   => null,
			    'secret_key' 	   => null,
			    'access_key' 	   => null,
			    'region' 		   => null,
			    'currency_code' 	   => null,
			    'sandbox' 		   => false,
			    'platform_id' 	   => null,
			    'cabundle_file' 	   => null,
			    'application_name'     => null,
			    'application_version'  => null,
                'proxy_tcp' 	   => null,
			    'proxy_host' 	   => null,
			    'proxy_port' 	   => -1,
			    'proxy_username' 	   => null,
			    'proxy_password' 	   => null,
			    'client_id' 	   => null,
			    'handle_throttle' 	   => true,
                'logger'            => null
			    );

    protected $modePath = null;
    // Number of microseconds to wait when initially throttled.  If throttled
    // repeatedly, wait time will be multiplied by a power of four.
    // Override this for services that require longer wait times.
    protected $basePause = 100000;

    // Final URL to where the API parameters POST done, based off the config['region'] and respective $mwsServiceUrls
    protected $mwsServiceUrl = null;
    protected $mwsServiceUrls;
    protected $profileEndpointUrls;
    protected $regionMappings;

    // Override in derived types to support structured list types
    protected $listPrefixes = array();
    protected $listMappings = array();

    // Boolean variable to check if the API call was a success
    public $success = false;

    /* Takes user configuration array from the user as input
     * Takes JSON file path with configuration information as input
     * Validates the user configuration array against existing config array
     */

    public function __construct($config = null)
    {
	$this->getRegionUrls();
        if (!is_null($config)) {

            if (is_array($config)) {
                $configArray = $config;
            } elseif (!is_array($config)) {
		$configArray = $this->checkIfFileExists($config);
	    }

	    if (is_array($configArray)) {
                $this->checkConfigKeys($configArray);
            } else {
                throw new \Exception('$config is of the incorrect type ' . gettype($configArray) . ' and should be of the type array');
            }
            if (empty($configArray['logger'])) {
                $this->logger = new NullLogger();
            } else {
                if ($configArray['logger'] instanceof LoggerInterface) {
                    $this->logger = $configArray['logger'];
                } else {
                    throw new \InvalidArgumentException(
                        'Logger passed in config must implement Psr\Log\LoggerInterface'
                    );
                }
            }
        } else {
	    throw new \Exception('$config cannot be null.');
	}
    }
    
    /* Get the Region specific properties from the Regions class.*/
    
    private function getRegionUrls()
    {
	$regionObject = new Regions();
	$this->mwsServiceUrls = $regionObject->mwsServiceUrls;
	$this->regionMappings = $regionObject->regionMappings;
	$this->profileEndpointUrls = $regionObject->profileEndpointUrls;
    }

    /* checkIfFileExists -  check if the JSON file exists in the path provided */

    private function checkIfFileExists($config)
    {
	if(file_exists($config))
	{
	    $jsonString  = file_get_contents($config);
	    $configArray = json_decode($jsonString, true);

	    $jsonError = json_last_error();

	    if ($jsonError != 0) {
		$errorMsg = "Error with message - content is not in json format" . $this->getErrorMessageForJsonError($jsonError) . " " . $configArray;
		throw new \Exception($errorMsg);
	    }
	} else {
	    $errorMsg ='$config is not a Json File path or the Json File was not found in the path provided';
	    throw new \Exception($errorMsg);
	}
	return $configArray;
    }

    /* Checks if the keys of the input configuration matches the keys in the config array
     * if they match the values are taken else throws exception
     * strict case match is not performed
     */

    private function checkConfigKeys($config)
    {
        $config = array_change_key_case($config, CASE_LOWER);
	    $config = ArrayUtil::trimArray($config);

        foreach ($config as $key => $value) {
            if (array_key_exists($key, $this->config)) {
                $this->config[$key] = $value;
            } else {
                throw new \Exception('Key ' . $key . ' is either not part of the configuration or has incorrect Key name.
				check the config array key names to match your key names of your config array', 1);
            }
        }
    }

    /* Convert a json error code to a descriptive error message
     *
     * @param int $jsonError message code
     *
     * @return string error message
     */

    private function getErrorMessageForJsonError($jsonError)
    {
        switch ($jsonError) {
            case JSON_ERROR_DEPTH:
                return " - maximum stack depth exceeded.";
                break;
            case JSON_ERROR_STATE_MISMATCH:
                return " - invalid or malformed JSON.";
                break;
            case JSON_ERROR_CTRL_CHAR:
                return " - control character error.";
                break;
            case JSON_ERROR_SYNTAX:
                return " - syntax error.";
                break;
            default:
                return ".";
                break;
        }
    }

    /* Setter for sandbox
     * Sets the Boolean value for config['sandbox'] variable
     */

    public function setSandbox($value)
    {
        if (is_bool($value)) {
            $this->config['sandbox'] = $value;
        } else {
            throw new \Exception($value . ' is of type ' . gettype($value) . ' and should be a boolean value');
        }
    }

    /* Setter for config['client_id']
     * Sets the value for config['client_id'] variable
     */

    public function setClientId($value)
    {
        if (!empty($value)) {
            $this->config['client_id'] = $value;
        } else {
            throw new \Exception('setter value for client ID provided is empty');
        }
    }

    /* Setter for Proxy
     * input $proxy [array]
     * @param $proxy['proxy_user_host'] - hostname for the proxy
     * @param $proxy['proxy_user_port'] - hostname for the proxy
     * @param $proxy['proxy_user_name'] - if your proxy required a username
     * @param $proxy['proxy_user_password'] - if your proxy required a password
     */

    public function setProxy($proxy)
    {
	if (!empty($proxy['proxy_user_host']))
	    $this->config['proxy_host'] = $proxy['proxy_user_host'];

        if (!empty($proxy['proxy_user_port']))
            $this->config['proxy_port'] = $proxy['proxy_user_port'];

        if (!empty($proxy['proxy_user_name']))
            $this->config['proxy_username'] = $proxy['proxy_user_name'];

        if (!empty($proxy['proxy_user_password']))
            $this->config['proxy_password'] = $proxy['proxy_user_password'];

        if (!empty($proxy['proxy_tcp'])) {
            $this->config['proxy_tcp'] = $proxy['proxy_tcp'];
        }
    }

    /* Setter for $mwsServiceUrl
     * Set the URL to which the post request has to be made for unit testing
     */

    public function setMwsServiceUrl($url)
    {
	$this->mwsServiceUrl = $url;
    }

    /* Getter
     * Gets the value for the key if the key exists in config
     */

    public function __get($name)
    {
        if (array_key_exists(strtolower($name), $this->config)) {
            return $this->config[strtolower($name)];
        } else {
            throw new \Exception('Key ' . $name . ' is either not a part of the configuration array config or the' . $name . 'does not match the key name in the config array', 1);
        }
    }

    /* Getter for parameters string
     * Gets the value for the parameters string for unit testing
     */

    public function getParameters()
    {
	return trim($this->parameters);
    }

    /* setParametersAndPost - sets the parameters array with non empty values from the requestParameters array sent to API calls.
     * If Provider Credit Details is present, values are set by setProviderCreditDetails
     * If Provider Credit Reversal Details is present, values are set by setProviderCreditDetails
     */

    protected function setParametersAndPost($parameters, $fieldMappings, $requestParameters, $parseResponse = true)
    {
	/* For loop to take all the non empty parameters in the $requestParameters and add it into the $parameters array,
	 * if the keys are matched from $requestParameters array with the $fieldMappings array
	 */
        foreach ($requestParameters as $param => $value) {

	    if(!is_array($value)) {
		$value = trim($value);
	    }

            if (array_key_exists($param, $fieldMappings) && $value!='') {

		if(is_array($value)) {
			$parameters = $this->setStructucturedListParameters($parameters, $fieldMappings[$param], $value);
		} else{
		    // For variables that are boolean values, strtolower them
		    if($this->checkIfBool($value))
		    {
			$value = strtolower($value);
		    }

		    $parameters[$fieldMappings[$param]] = $value;
		}
            }
        }

        $parameters = $this->setDefaultValues($parameters, $fieldMappings, $requestParameters);
	$responseObject = $this->calculateSignatureAndPost($parameters, $parseResponse);

	return $responseObject;
    }

    protected function setStructucturedListParameters($parameters, $name, $list) {
        $listIndex = 0;
        $listPrefix = $this->listPrefixes[$name];

        if (isset($this->listMappings[$name])) {
            $fieldMappings = $this->listMappings[$name];
        }

        foreach ($list as $key => $value)
        {
            $listIndex = $listIndex + 1;

            if ( is_array( $value ) ) {
                $value = array_change_key_case($value, CASE_LOWER);
                foreach ($value as $param => $val)
                {
                    if (array_key_exists($param, $fieldMappings) && trim($val)!='') {
                        $parameters["{$listPrefix}.{$listIndex}.{$fieldMappings[$param]}"] = $val;
                    }
                }
                // Special case: if currency code is mapped but not provided,
                // take it from the config array
                if (isset($fieldMappings['currency_code'])) {
                    $currencyKey = "{$listPrefix}.{$listIndex}.{$fieldMappings['currency_code']}";
                    if(empty($parameters[$currencyKey])) {
                        $parameters[$currencyKey] = strtoupper($this->config['currency_code']);
                    }
                }
            } else {
                $parameters["{$listPrefix}.{$listIndex}"] = $value;
            }
        }

        return $parameters;
    }

    /* checkIfBool - checks if the input is a boolean */
    
    private function checkIfBool($string)
    {
	$string = strtolower($string);
	return in_array($string, array('true', 'false'));
    }

    /* calculateSignatureAndPost - convert the Parameters array to string and curl POST the parameters to MWS */

    private function calculateSignatureAndPost($parameters, $parseResponse = true)
    {
	// Call the signature and Post function to perform the actions. Returns XML in array format
        $parametersString = $this->calculateSignatureAndParametersToString($parameters);

	// POST using curl the String converted Parameters
	$response = $this->invokePost($parametersString);
		if ( $parseResponse ) {
		// Send this response as args to ResponseParser class which will return the object of the class.
			$responseObject = new ResponseParser($response);
			return $responseObject;
		}
		return $response;
    }

    /* If merchant_id is not set via the requestParameters array then it's taken from the config array
     *
     * Set the platform_id if set in the config['platform_id'] array
     *
     * If currency_code is set in the $requestParameters and it exists in the $fieldMappings array, strtoupper it
     * else take the value from config array if set
     */

    private function setDefaultValues($parameters, $fieldMappings, $requestParameters)
    {
        if (empty($requestParameters['merchant_id']))
            $parameters['SellerId'] = $this->config['merchant_id'];

        if (array_key_exists('platform_id', $fieldMappings)) {
	    if (empty($requestParameters['platform_id']) && !empty($this->config['platform_id']))
            $parameters[$fieldMappings['platform_id']] = $this->config['platform_id'];
	}

        if (array_key_exists('currency_code', $fieldMappings)) {
            if (!empty($requestParameters['currency_code'])) {
		$parameters[$fieldMappings['currency_code']] = strtoupper($requestParameters['currency_code']);
            } else {
                $parameters[$fieldMappings['currency_code']] = strtoupper($this->config['currency_code']);
            }
        }

        return $parameters;
    }

    /* Create an Array of required parameters, sort them
     * Calculate signature and invoke the POST to the MWS Service URL
     *
     * @param AWSAccessKeyId [String]
     * @param Version [String]
     * @param SignatureMethod [String]
     * @param Timestamp [String]
     * @param Signature [String]
     */

    private function calculateSignatureAndParametersToString($parameters = array())
    {
        $parameters['AWSAccessKeyId']   = $this->config['access_key'];
        $parameters['Version']          = $this->serviceVersion;
        $parameters['SignatureMethod']  = 'HmacSHA256';
        $parameters['SignatureVersion'] = 2;
        $parameters['Timestamp']        = $this->getFormattedTimestamp();
        uksort($parameters, 'strcmp');

        $this->createServiceUrl();

        $parameters['Signature'] = $this->signParameters($parameters);
        $parameters              = $this->getParametersAsString($parameters);

	// Save these parameters in the parameters variable so that it can be returned for unit testing.
	$this->parameters 	 = $parameters;
        return $parameters;
    }

    /* Computes RFC 2104-compliant HMAC signature for request parameters
     * Implements AWS Signature, as per following spec:
     *
     * If Signature Version is 0, it signs concatenated Action and Timestamp
     *
     * If Signature Version is 1, it performs the following:
     *
     * Sorts all  parameters (including SignatureVersion and excluding Signature,
     * the value of which is being created), ignoring case.
     *
     * Iterate over the sorted list and append the parameter name (in original case)
     * and then its value. It will not URL-encode the parameter values before
     * constructing this string. There are no separators.
     *
     * If Signature Version is 2, string to sign is based on following:
     *
     *    1. The HTTP Request Method followed by an ASCII newline (%0A)
     *    2. The HTTP Host header in the form of lowercase host, followed by an ASCII newline.
     *    3. The URL encoded HTTP absolute path component of the URI
     *       (up to but not including the query string parameters);
     *       if this is empty use a forward '/'. This parameter is followed by an ASCII newline.
     *    4. The concatenation of all query string components (names and values)
     *       as UTF-8 characters which are URL encoded as per RFC 3986
     *       (hex characters MUST be uppercase), sorted using lexicographic byte ordering.
     *       Parameter names are separated from their values by the '=' character
     *       (ASCII character 61), even if the value is empty.
     *       Pairs of parameter and values are separated by the '&' character (ASCII code 38).
     *
     */

    private function signParameters(array $parameters)
    {
        $signatureVersion = $parameters['SignatureVersion'];
        $algorithm        = "HmacSHA1";
        $stringToSign     = null;
        if (2 === $signatureVersion) {
            $algorithm                     = "HmacSHA256";
            $parameters['SignatureMethod'] = $algorithm;
            $stringToSign                  = $this->calculateStringToSignV2($parameters);
        } else {
            throw new \Exception("Invalid Signature Version specified");
        }

        return $this->sign($stringToSign, $algorithm);
    }

    /* Calculate String to Sign for SignatureVersion 2
     * @param array $parameters request parameters
     * @return String to Sign
     */

    private function calculateStringToSignV2(array $parameters)
    {
        $data = 'POST';
        $data .= "\n";
        $data .= $this->mwsEndpointUrl;
        $data .= "\n";
        $data .= $this->mwsEndpointPath;
        $data .= "\n";
        $data .= $this->getParametersAsString($parameters);
        return $data;
    }

    /* Convert paremeters to Url encoded query string */

    private function getParametersAsString(array $parameters)
    {
        $queryParameters = array();
        foreach ($parameters as $key => $value) {
            $queryParameters[] = $key . '=' . $this->urlEncode($value);
        }

        return implode('&', $queryParameters);
    }

    private function urlEncode($value)
    {
        return str_replace('%7E', '~', rawurlencode($value));
    }

    /* Computes RFC 2104-compliant HMAC signature */

    private function sign($data, $algorithm)
    {
        if ($algorithm === 'HmacSHA1') {
            $hash = 'sha1';
        } else if ($algorithm === 'HmacSHA256') {
            $hash = 'sha256';
        } else {
            throw new \Exception("Non-supported signing method specified");
        }

        return base64_encode(hash_hmac($hash, $data, $this->config['secret_key'], true));
    }

    /* Formats date as ISO 8601 timestamp */

    private function getFormattedTimestamp()
    {
        return gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time());
    }

    /* invokePost takes the parameters and invokes the httpPost function to POST the parameters
     * Exponential retries on error 500 and 503
     * The response from the POST is an XML which is converted to Array
     */

    private function invokePost($parameters)
    {
        $response       = array();
        $statusCode     = 200;
        $this->success = false;

	// Submit the request and read response body
	try {
            $shouldRetry = true;
            $retries     = 0;
            do {
                try {
                    $this->constructUserAgentHeader();

                    $httpCurlRequest = new HttpCurl($this->config);
                    $response = $httpCurlRequest->httpPost($this->mwsServiceUrl, $this->userAgent, $parameters);
                    $curlResponseInfo = $httpCurlRequest->getCurlResponseInfo();

                    $statusCode = $curlResponseInfo["http_code"];

                    $response = array(
                        'Status' => $statusCode,
                        'ResponseBody' => $response
                    );

                    if ($statusCode == 200) {
                        $shouldRetry = false;
                        $this->success = true;
                    } elseif ($statusCode == 500 || $statusCode == 503) {

                        $shouldRetry = true;
                        if ($shouldRetry && strtolower($this->config['handle_throttle'])) {
                            $this->pauseOnRetry(++$retries, $statusCode);
                        }
                    } else {
                        $this->logger->info("Returned status code $statusCode, not retrying.");
                        $shouldRetry = false;
                    }
                } catch (\Exception $e) {
                    throw $e;
                }
            } while ($shouldRetry);
        } catch (\Exception $se) {
            throw $se;
        }

        return $response;
    }

    /* Exponential sleep on failed request
     * @param retries current retry
     * @throws Exception if maximum number of retries has been reached
     */

    private function pauseOnRetry($retries, $status)
    {
        if ($retries <= self::MAX_ERROR_RETRY) {
            $delay = (int) (pow(4, $retries) * $this->basePause);
            $this->logger->info("Returned status code $status on try $retries, waiting $delay microseconds.");
            usleep($delay);
        } else {
            throw new \Exception('Error Code: '. $status.PHP_EOL.'Maximum number of retry attempts - '. $retries .' reached');
        }
    }

	
    /* Create MWS service URL and the Endpoint path */
    private function createServiceUrl()
    {
		$this->setModePath();

        if (!empty($this->config['region'])) {
            $region = strtolower($this->config['region']);
            if (array_key_exists($region, $this->regionMappings)) {
                $this->mwsEndpointUrl  = $this->mwsServiceUrls[$this->regionMappings[$region]];
                $this->mwsServiceUrl   = 'https://' . $this->mwsEndpointUrl . '/' . $this->modePath . '/' . $this->serviceVersion;
                $this->mwsEndpointPath = '/' . $this->modePath . '/' . $this->serviceVersion;
            } else {
                throw new \Exception($region . ' is not a valid region');
            }
        } else {
            throw new \Exception("config['region'] is a required parameter and is not set");
        }
    }

	abstract protected function setModePath();

    /* Create the User Agent Header sent with the POST request */

    private function constructUserAgentHeader()
    {
        $this->userAgent = $this->quoteApplicationName($this->config['application_name']) . '/' . $this->quoteApplicationVersion($this->config['application_version']);
        $this->userAgent .= ' (';
        $this->userAgent .= 'Language=PHP/' . phpversion();
        $this->userAgent .= '; ';
        $this->userAgent .= 'Platform=' . php_uname('s') . '/' . php_uname('m') . '/' . php_uname('r');
        $this->userAgent .= '; ';
        $this->userAgent .= 'MWSClientVersion=' . self::MWS_CLIENT_VERSION;
        $this->userAgent .= ')';
    }

    /* Collapse multiple whitespace characters into a single ' ' and backslash escape '\',
     * and '/' characters from a string.
     * @param $s
     * @return string
     */

    private function quoteApplicationName($s)
    {
        $quotedString = preg_replace('/ {2,}|\s/', ' ', $s);
        $quotedString = preg_replace('/\\\\/', '\\\\\\\\', $quotedString);
        $quotedString = preg_replace('/\//', '\\/', $quotedString);
        return $quotedString;
    }

    /* Collapse multiple whitespace characters into a single ' ' and backslash escape '\',
     * and '(' characters from a string.
     *
     * @param $s
     * @return string
     */

    private function quoteApplicationVersion($s)
    {
        $quotedString = preg_replace('/ {2,}|\s/', ' ', $s);
        $quotedString = preg_replace('/\\\\/', '\\\\\\\\', $quotedString);
        $quotedString = preg_replace('/\\(/', '\\(', $quotedString);
        return $quotedString;
    }
}
