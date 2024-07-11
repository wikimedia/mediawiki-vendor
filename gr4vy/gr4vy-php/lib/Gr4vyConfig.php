<?php

namespace Gr4vy;

use Gr4vy\Configuration as Gr4vyConfiguration; 
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\LocalFileReference;
use Lcobucci\JWT\Signer\Key\InMemory;
use DateTimeImmutable;

class Gr4vyConfig
{
    protected $gr4vyId;
    protected $privateKeyLocation;
    protected $host;
    protected $debug = false;
    protected $environment;
    protected $merchantAccountId = "";

    /**
     * Constructor
     */
    public function __construct($gr4vyId, $privateKeyLocation, $debug=false, $environment="sandbox", $merchantAccountId="")
    {
        $this->gr4vyId = $gr4vyId;
        $this->privateKeyLocation = $privateKeyLocation;
        $this->debug = $debug;
        $this->environment = $environment;
        $apiPrefix = $environment === "sandbox" ? "sandbox." : "";
        $this->host = "https://api." . $apiPrefix . $gr4vyId .".gr4vy.app";
        $this->merchantAccountId = $merchantAccountId;
    }

    public function setGr4vyId($gr4vyId)
    {
        $this->gr4vyId = $gr4vyId;
        return $this;
    }

    public function getGr4vyId()
    {
        return $this->$gr4vyId;
    }

    public function setMerchantAccountId($merchantAccountId)
    {
        $this->merchantAccountId = $merchantAccountId;
        return $this;
    }

    public function getMerchantAccountId()
    {
        return $this->$merchantAccountId;
    }

    public function setPrivateKeyLocation($privateKeyLocation)
    {
        $this->privateKeyLocation = $privateKeyLocation;
        return $this;
    }

    public function getPrivateKeyLocation()
    {
        return $this->privateKeyLocation;
    }

    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
        return $this;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function getEmbedToken($embed, $checkoutSessionId = null) {
        $scopes = array("embed");
        return self::getToken($this->privateKeyLocation, $scopes, $embed, $checkoutSessionId);
    }

    public function getEmbedTokenWithCheckoutSession($embed) {
        $scopes = array("embed");
        $checkoutSession = $this->newCheckoutSession();
        return self::getToken($this->privateKeyLocation, $scopes, $embed, $checkoutSession["id"]);
    }

    public static function getToken($private_key, $scopes = array(), $embed = array(), $checkoutSessionId = null) {

        $keyVal = getenv("PRIVATE_KEY");
        if (!isset($keyVal) || empty($keyVal)) {
            $keyVal = file_get_contents($private_key);
        }
        $key = InMemory::plainText($keyVal);
        
        $config = Configuration::forAsymmetricSigner(
            // You may use RSA or ECDSA and all their variations (256, 384, and 512) and EdDSA over Curve25519
            Signer\Ecdsa\Sha512::create(),
            $key,
            InMemory::base64Encoded('bm90dXNlZA==')
        );


        $kid = self::getThumbprint($keyVal);

        $now   = new DateTimeImmutable();
        $tokenBuilder = $config->builder()
                // Configures the issuer (iss claim)
                ->issuedBy('Gr4vy SDK 0.21.0')
                // Configures the id (jti claim)
                ->identifiedBy(self::gen_uuid())
                // Configures the time that the token was issue (iat claim)
                ->issuedAt($now)
                // Configures the time that the token can be used (nbf claim)
                ->canOnlyBeUsedAfter($now)#->modify('+1 minute'))
                // Configures the expiration time of the token (exp claim)
                ->expiresAt($now->modify('+1 hour'))
                // Configures a new claim, called "uid"
                ->withClaim('scopes', $scopes)
                // // Configures a new header, called "foo"
                ->withHeader('kid', $kid);

        if (isset($embed) && count($embed) > 0) {
            $tokenBuilder = $tokenBuilder->withClaim('embed', $embed);    
        }

        if (isset($checkoutSessionId)) {
            $tokenBuilder = $tokenBuilder->withClaim('checkout_session_id', $checkoutSessionId);
        }

        return $tokenBuilder->getToken($config->signer(), $config->signingKey())->toString();
    }

    private static function gen_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    private static function getThumbprint($private_key) {
        $privateKey = openssl_pkey_get_private($private_key);
        $keyInfo = openssl_pkey_get_details($privateKey);

        $n = $keyInfo["bits"] / 8;

        if ($keyInfo["bits"]%8 != 0) {
            $n++;
        }
        $n = intval($n);
        $x_byte_array = unpack('C*', $keyInfo['ec']['x']);
        $y_byte_array = unpack('C*', $keyInfo['ec']['y']);

        if ($n > count($x_byte_array)) {
            $byte = array(0);
            $x_byte_array = array_merge($byte, $x_byte_array);
        }
        if ($n > count($y_byte_array)) {
            $byte = array(0);
            $y_byte_array = array_merge($byte, $y_byte_array);
        }

        $xStr = pack('C*', ...$x_byte_array);
        $yStr = pack('C*', ...$y_byte_array);

        $jsonData = array(
                'crv' => "P-521",//$keyInfo['ec']["curve_name"],
                'kty' => 'EC',
                'x' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($xStr)), '='),
                'y' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($yStr)), '='),
        );

        $data = json_encode($jsonData);
        $b = hash("SHA256", $data, true);
        return rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($b)), '=');
    }

    private function get($endpoint, $params = array()) {
        $query = "";
        if (count($params) > 0) {
            $query = http_build_query($params);
        }
        $url = $this->host . $endpoint . "?" . $query;

        $scopes = array("*.read", "*.write");
        $accessToken = self::getToken($this->privateKeyLocation, $scopes);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type:application/json',
                "X-GR4VY-MERCHANT-ACCOUNT-ID:" . $this->merchantAccountId
            )
        );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if(curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return json_decode($responseData, true);
    }

    private function post($endpoint, $data) {
        $url = $this->host . $endpoint;

        $scopes = array("*.read", "*.write");
        $accessToken = self::getToken($this->privateKeyLocation, $scopes);

        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type:application/json',
                "X-GR4VY-MERCHANT-ACCOUNT-ID:" . $this->merchantAccountId
            )
        );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if(curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return json_decode($responseData, true);
    }

    private function put($endpoint, $data) {
        $url = $this->host . $endpoint;

        $scopes = array("*.read", "*.write");
        $accessToken = self::getToken($this->privateKeyLocation, $scopes);

        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type:application/json',
                "X-GR4VY-MERCHANT-ACCOUNT-ID:" . $this->merchantAccountId
            )
        );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if(curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return json_decode($responseData, true);
    }

    private function delete($endpoint) {
        $url = $this->host . $endpoint;
        $scopes = array("*.read", "*.write");
        $accessToken = self::getToken($this->privateKeyLocation, $scopes);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type:application/json',
                "X-GR4VY-MERCHANT-ACCOUNT-ID:" . $this->merchantAccountId
            )
        );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $responseData = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if(curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        if ($httpcode === 204) {
            return array("success"=>true);    
        }
        return json_decode($responseData, true);
    }
    
    public function addBuyer($buyer_request) {
        $response = $this->post("/buyers", $buyer_request);
        return $response;
    }
    public function getBuyer($buyer_id) {
        $response = $this->get("/buyers/" . $buyer_id);
        return $response;
    }
    public function updateBuyer($buyer_id, $buyer_request) {
        $response = $this->put("/buyers/" . $buyer_id, $buyer_request);
        return $response;
    }
    public function listBuyers($params = array()) {
        $response = $this->get("/buyers", $params);
        return $response;
    }
    public function deleteBuyer($buyer_id) {
        $response = $this->delete("/buyers/" . $buyer_id);
        return $response;
    }

    public function storePaymentMethod($payment_method_request) {
        $response = $this->post("/payment-methods", $payment_method_request);
        return $response;
    }
    public function getPaymentMethod($payment_method_id) {
        $response = $this->get("/payment-methods/" . $payment_method_id);
        return $response;
    }
    public function listPaymentMethods($params = array()) {
        $response = $this->get("/payment-methods", $params);
        return $response;
    }
    public function listBuyerPaymentMethods($buyer_id) {
        $response = $this->get("/buyers/payment-methods?buyer_id=" . $buyer_id);
        return $response;
    }
    public function deletePaymentMethod($buyer_id) {
        $response = $this->delete("/payment-methods/" . $buyer_id);
        return $response;
    }

    public function listPaymentOptions($params = array()) {
        $response = $this->get("/payment-options", $params);
        return $response;
    }
    public function postListPaymentOptions($payment_options_request) {
        $response = $this->post("/payment-options", $payment_options_request);
        return $response;
    }

    public function listPaymentServiceDefinitions($params = array()) {
        $response = $this->get("/payment-service-definitions", $params);
        return $response;
    }
    public function getPaymentServiceDefinition($psd_id) {
        $response = $this->get("/payment-service-definitions/" . $psd_id);
        return $response;
    }

    public function addPaymentService($payment_service_request) {
        $response = $this->post("/payment-services", $payment_service_request);
        return $response;
    }
    public function getPaymentService($payment_service_id) {
        $response = $this->get("/payment-services/" . $payment_service_id);
        return $response;
    }
    public function updatePaymentService($payment_service_id, $payment_service_request) {
        $response = $this->put("/payment-services/" . $payment_service_id, $payment_service_request);
        return $response;
    }
    public function listPaymentServices($params = array()) {
        $response = $this->get("/payment-services", $params);
        return $response;
    }
    public function deletePaymentService($payment_service_id) {
        $response = $this->delete("/payment-services/" . $payment_service_id);
        return $response;
    }
    public function authorizeNewTransaction($transaction_request) {
        $response = $this->post("/transactions", $transaction_request);
        return $response;
    }
    public function getTransaction($transaction_id) {
        $response = $this->get("/transactions/" . $transaction_id);
        return $response;
    }
    public function captureTransaction($transaction_id, $transaction_request) {
        $response = $this->post("/transactions/" . $transaction_id . "/capture", $transaction_request);
        return $response;
    }
    public function listTransactions($params = array()) {
        $response = $this->get("/transactions", $params);
        return $response;
    }
    public function refundTransaction($transaction_id, $refund_request) {
        $response = $this->post("/transactions/" . $transaction_id . "/refunds", $refund_request);
        return $response;
    }
    public function voidTransaction($transaction_id, $request = array()) {
        $response = $this->post("/transactions/" . $transaction_id . "/void", $request);
        return $response;
    }
    public function newCheckoutSession($request = array()) {
        $response = $this->post("/checkout/sessions", $request);
        return $response;
    }
    public function updateCheckoutSession($request = array()) {
        $response = $this->put("/checkout/sessions/" . $checkout_session_id, $request);
        return $response;
    }
    public function updateCheckoutSessionFields($request = array()) {
        $response = $this->put("/checkout/sessions/" . $checkout_session_id . "/fields", $request);
        return $response;
    }
    public function deleteCheckoutSession() {
        $response = $this->delete("/checkout/sessions/" . $checkout_session_id);
        return $response;
    }
}
