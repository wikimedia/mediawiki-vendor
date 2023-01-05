<?php
namespace PayWithAmazon;

/* Class HttpCurl
 * Handles Curl POST function for all requests
 */

require_once 'HttpCurlInterface.php';

class HttpCurl implements HttpCurlInterface
{
    private $config = array();
    private $header = false;
    private $requestHeaders = array();
    private $accessToken = null;
    private $curlResponseInfo = null;
    
    /* Takes user configuration array as input
     * Takes configuration for API call or IPN config
     */
    
    public function __construct($config = null)
    {
        $this->config = $config;
    }
    
    /* Setter for boolean header to get the user info */
    
    public function setHttpHeader()
    {
        $this->header = true;
    }

    protected function addRequestHeader($ch, $header)
    {
        array_push($this->requestHeaders, $header);
        curl_setopt($ch, CURLOPT_HEADER, $this->requestHeaders);
    }

    /* Setter for Access token to get the user info */
    
    public function setAccessToken($accesstoken)
    {
        $this->accessToken = $accesstoken;
    }

    /* Add the common Curl Parameters to the curl handler $ch
     * Also checks for optional parameters if provided in the config
     * config['cabundle_file']
     * config['proxy_tcp']
     * config['proxy_port']
     * config['proxy_host']
     * config['proxy_username']
     * config['proxy_password']
     */
    
    private  function commonCurlParams($url,$userAgent)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if (!is_null($this->config['cabundle_file'])) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->config['cabundle_file']);
        }
        
        if (!empty($userAgent))
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

        if ($this->config['proxy_tcp'] != null) {
            // Support TCP proxy. Use CURLOPT_RESOLVE to make sure we
            // use the IP address set in proxy_tcp rather than what
            // would otherwise come back from DNS.
            $resolve = array();
            if (is_array($this->config['proxy_tcp'])) {
                // Only proxy traffic for specified hosts
                foreach ($this->config['proxy_tcp'] as $proxyHost => $proxyIp) {
                    $resolve[] = "$proxyHost:443:$proxyIp";
                }
            } else {
                // Proxy whatever host we're trying to send to
                $urlParts = parse_url($url);
                $hostName = $urlParts['host'];
                $resolve[] = "$hostName:443:{$this->config['proxy_tcp']}";
            }
            if (!empty($resolve)) {
                curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
            }
        }
        elseif ($this->config['proxy_host'] != null && $this->config['proxy_port'] != -1) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy_host'] . ':' . $this->config['proxy_port']);
        }
        
        if ($this->config['proxy_username'] != null && $this->config['proxy_password'] != null) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->config['proxy_username'] . ':' . $this->config['proxy_password']);
        }
        
        return $ch;
    }
    
    /* POST using curl for the following situations
     * 1. API calls
     * 2. IPN certificate retrieval
     * 3. Get User Info
     */
    
    public function httpPost($url, $userAgent = null, $parameters = null)
    {
        $ch = $this->commonCurlParams($url,$userAgent);
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        
        $response = $this->execute($ch);
        return $response;
    }
    
    /* GET using curl for the following situations
     * 1. IPN certificate retrieval
     * 2. Get User Info
     */
    
    public function httpGet($url, $userAgent = null)
    {
        $ch = $this->commonCurlParams($url,$userAgent);
        
        // Setting the HTTP header with the Access Token only for Getting user info
        if ($this->header) {
            $this->addRequestHeader($ch, 'Authorization: bearer ' . $this->accessToken);
        }
        
        $response = $this->execute($ch);
        return $response;
    }
    
    /* Execute Curl request */
    
    private function execute($ch)
    {
        $debugLog = fopen('php://temp', 'r+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $debugLog);
        $response = curl_exec($ch);
        // Always read the verbose output
        rewind($debugLog);
        $logged = fread($debugLog, 8192);
        fclose($debugLog);
        if (!$response) {
            $error_msg = "Unable to post request, underlying exception of " . curl_error($ch);
            $error_msg .= " Debug logging: $logged";
            curl_close($ch);
            throw new \Exception($error_msg);
        }
        else{
            $this->curlResponseInfo = curl_getinfo($ch);
        }
        curl_close($ch);
        return $response;
    }

    /* Get the output of Curl Getinfo */

    public function getCurlResponseInfo()
    {
        return $this->curlResponseInfo;
    }
}
