<?php
namespace PayWithAmazon;

require_once 'BaseClient.php';
require_once 'PaymentsClientInterface.php';

class PaymentsClient extends BaseClient implements PaymentsClientInterface{

    private $profileEndpoint = null;

    protected $serviceVersion = '2013-01-01';

    // Overriding to support ProviderCreditList and ProviderCreditReversalList
    protected $listPrefixes = array(
        'ProviderCreditList' => 'ProviderCreditList.member',
        'ProviderCreditReversalList' => 'ProviderCreditReversalList.member',
    );

    protected $listMappings = array(
        'ProviderCreditList' => array(
            'provider_id'   => 'ProviderId',
            'credit_amount' => 'CreditAmount.Amount',
            'currency_code' => 'CreditAmount.CurrencyCode'
        ),
        'ProviderCreditReversalList' => array(
            'provider_id'            => 'ProviderId',
            'credit_reversal_amount'     => 'CreditReversalAmount.Amount',
            'currency_code'         => 'CreditReversalAmount.CurrencyCode'
        ),
    );

    /* GetUserInfo convenience function - Returns user's profile information from Amazon using the access token returned by the Button widget.
     *
     * @see http://login.amazon.com/website Step 4
     * @param $accessToken [String]
     */

    public function getUserInfo($accessToken)
    {
        // Get the correct Profile Endpoint URL based off the country/region provided in the config['region']
        $this->profileEndpointUrl();

        if (empty($accessToken)) {
            throw new \InvalidArgumentException('Access Token is a required parameter and is not set');
        }

        // To make sure double encoding doesn't occur decode first and encode again.
        $accessToken = urldecode($accessToken);
        $url 	     = $this->profileEndpoint . '/auth/o2/tokeninfo?access_token=' . urlEncode($accessToken);

        $httpCurlRequest = new HttpCurl($this->config);

        $response = $httpCurlRequest->httpGet($url);
        $data 	  = json_decode($response);

        if ($data->aud != $this->config['client_id']) {
            // The access token does not belong to us
            throw new \Exception('The Access token entered is incorrect');
        }

        // Exchange the access token for user profile
        $url             = $this->profileEndpoint . '/user/profile';
        $httpCurlRequest = new HttpCurl($this->config);

        $httpCurlRequest->setAccessToken($accessToken);
        $httpCurlRequest->setHttpHeader(true);
        $response = $httpCurlRequest->httpGet($url);

        $userInfo = json_decode($response, true);
        return $userInfo;
    }

    /* GetOrderReferenceDetails API call - Returns details about the Order Reference object and its current state.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_GetOrderReferenceDetails.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_order_reference_id'] - [String]
     * @optional requestParameters['address_consent_token'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getOrderReferenceDetails($requestParameters = array())
    {

        $parameters['Action'] = 'GetOrderReferenceDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_order_reference_id' => 'AmazonOrderReferenceId',
            'address_consent_token' 	=> 'AddressConsentToken',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);
        return ($responseObject);
    }

    /* SetOrderReferenceDetails API call - Sets order reference details such as the order total and a description for the order.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_SetOrderReferenceDetails.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_order_reference_id'] - [String]
     * @param requestParameters['amount'] - [String]
     * @param requestParameters['currency_code'] - [String]
     * @optional requestParameters['platform_id'] - [String]
     * @optional requestParameters['seller_note'] - [String]
     * @optional requestParameters['seller_order_id'] - [String]
     * @optional requestParameters['store_name'] - [String]
     * @optional requestParameters['custom_information'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function setOrderReferenceDetails($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'SetOrderReferenceDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_order_reference_id' => 'AmazonOrderReferenceId',
            'amount' 			=> 'OrderReferenceAttributes.OrderTotal.Amount',
            'currency_code' 		=> 'OrderReferenceAttributes.OrderTotal.CurrencyCode',
            'platform_id' 		=> 'OrderReferenceAttributes.PlatformId',
            'seller_note' 		=> 'OrderReferenceAttributes.SellerNote',
            'seller_order_id' 		=> 'OrderReferenceAttributes.SellerOrderAttributes.SellerOrderId',
            'store_name' 		=> 'OrderReferenceAttributes.SellerOrderAttributes.StoreName',
            'custom_information'	=> 'OrderReferenceAttributes.SellerOrderAttributes.CustomInformation',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

		return ($responseObject);
    }

    /* ConfirmOrderReferenceDetails API call - Confirms that the order reference is free of constraints and all required information has been set on the order reference.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_ConfirmOrderReference.html

     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_order_reference_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function confirmOrderReference($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'ConfirmOrderReference';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_order_reference_id' => 'AmazonOrderReferenceId',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* CancelOrderReferenceDetails API call - Cancels a previously confirmed order reference.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_CancelOrderReference.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_order_reference_id'] - [String]
     * @optional requestParameters['cancelation_reason'] [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function cancelOrderReference($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'CancelOrderReference';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_order_reference_id' => 'AmazonOrderReferenceId',
            'cancelation_reason' 	=> 'CancelationReason',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

		return ($responseObject);
    }

    /* CloseOrderReferenceDetails API call - Confirms that an order reference has been fulfilled (fully or partially)
     * and that you do not expect to create any new authorizations on this order reference.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_CloseOrderReference.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_order_reference_id'] - [String]
     * @optional requestParameters['closure_reason'] [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function closeOrderReference($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'CloseOrderReference';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_order_reference_id' => 'AmazonOrderReferenceId',
            'closure_reason' 		=> 'ClosureReason',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* CloseAuthorization API call - Closes an authorization.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_CloseOrderReference.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_authorization_id'] - [String]
     * @optional requestParameters['closure_reason'] [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function closeAuthorization($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'CloseAuthorization';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_authorization_id' 	=> 'AmazonAuthorizationId',
            'closure_reason' 		=> 'ClosureReason',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* Authorize API call - Reserves a specified amount against the payment method(s) stored in the order reference.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_Authorize.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_order_reference_id'] - [String]
     * @param requestParameters['authorization_amount'] [String]
     * @param requestParameters['currency_code'] - [String]
     * @param requestParameters['authorization_reference_id'] [String]
     * @optional requestParameters['capture_now'] [String]
     * @optional requestParameters['provider_credit_details'] - [array (array())]
     * @optional requestParameters['seller_authorization_note'] [String]
     * @optional requestParameters['transaction_timeout'] [String] - Defaults to 1440 minutes
     * @optional requestParameters['soft_descriptor'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function authorize($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'Authorize';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		 => 'SellerId',
            'amazon_order_reference_id'  => 'AmazonOrderReferenceId',
            'authorization_amount' 	 => 'AuthorizationAmount.Amount',
            'currency_code' 		 => 'AuthorizationAmount.CurrencyCode',
            'authorization_reference_id' => 'AuthorizationReferenceId',
            'capture_now' 		 => 'CaptureNow',
	    'provider_credit_details'	 => array(),
            'seller_authorization_note'  => 'SellerAuthorizationNote',
            'transaction_timeout' 	 => 'TransactionTimeout',
            'soft_descriptor' 		 => 'SoftDescriptor',
            'mws_auth_token' 		 => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* GetAuthorizationDetails API call - Returns the status of a particular authorization and the total amount captured on the authorization.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_GetAuthorizationDetails.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_authorization_id'] [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getAuthorizationDetails($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'GetAuthorizationDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_authorization_id' 	=> 'AmazonAuthorizationId',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* Capture API call - Captures funds from an authorized payment instrument.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_Capture.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_authorization_id'] - [String]
     * @param requestParameters['capture_amount'] - [String]
     * @param requestParameters['currency_code'] - [String]
     * @param requestParameters['capture_reference_id'] - [String]
     * @optional requestParameters['provider_credit_details'] - [array (array())]
     * @optional requestParameters['seller_capture_note'] - [String]
     * @optional requestParameters['soft_descriptor'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function capture($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'Capture';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_authorization_id' 	=> 'AmazonAuthorizationId',
            'capture_amount' 		=> 'CaptureAmount.Amount',
            'currency_code' 		=> 'CaptureAmount.CurrencyCode',
            'capture_reference_id' 	=> 'CaptureReferenceId',
	    'provider_credit_details'	=> array(),
            'seller_capture_note' 	=> 'SellerCaptureNote',
            'soft_descriptor' 		=> 'SoftDescriptor',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

		return ($responseObject);
    }

    /* GetCaptureDetails API call - Returns the status of a particular capture and the total amount refunded on the capture.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_GetCaptureDetails.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_capture_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getCaptureDetails($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'GetCaptureDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 	=> 'SellerId',
            'amazon_capture_id' => 'AmazonCaptureId',
            'mws_auth_token' 	=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* Refund API call - Refunds a previously captured amount.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_Refund.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_capture_id'] - [String]
     * @param requestParameters['refund_reference_id'] - [String]
     * @param requestParameters['refund_amount'] - [String]
     * @param requestParameters['currency_code'] - [String]
     * @optional requestParameters['provider_credit_reversal_details'] - [array(array())]
     * @optional requestParameters['seller_refund_note'] [String]
     * @optional requestParameters['soft_descriptor'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function refund($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'Refund';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 	  		=> 'SellerId',
            'amazon_capture_id'   		=> 'AmazonCaptureId',
            'refund_reference_id' 		=> 'RefundReferenceId',
            'refund_amount' 	  		=> 'RefundAmount.Amount',
            'currency_code' 	  		=> 'RefundAmount.CurrencyCode',
			'provider_credit_reversal_details'	=> array(),
            'seller_refund_note'  		=> 'SellerRefundNote',
            'soft_descriptor' 	  		=> 'SoftDescriptor',
            'mws_auth_token' 	  		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* GetRefundDetails API call - Returns the status of a particular refund.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_GetRefundDetails.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_refund_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getRefundDetails($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'GetRefundDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 	=> 'SellerId',
            'amazon_refund_id'  => 'AmazonRefundId',
            'mws_auth_token' 	=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* GetServiceStatus API Call - Returns the operational status of the Off-Amazon Payments API section
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_GetServiceStatus.html
     *
     * The GetServiceStatus operation returns the operational status of the Off-Amazon Payments API
     * section of Amazon Marketplace Web Service (Amazon MWS).
     * Status values are GREEN, GREEN_I, YELLOW, and RED.
     *
     * @param requestParameters['merchant_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getServiceStatus($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'GetServiceStatus';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id'    => 'SellerId',
            'mws_auth_token' => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

		return ($responseObject);
    }

    /* CreateOrderReferenceForId API Call - Creates an order reference for the given object
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_CreateOrderReferenceForId.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['Id'] - [String]
     * @optional requestParameters['inherit_shipping_address'] [Boolean]
     * @optional requestParameters['ConfirmNow'] - [Boolean]
     * @optional Amount (required when confirm_now is set to true) [String]
     * @optional requestParameters['currency_code'] - [String]
     * @optional requestParameters['seller_note'] - [String]
     * @optional requestParameters['seller_order_id'] - [String]
     * @optional requestParameters['store_name'] - [String]
     * @optional requestParameters['custom_information'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function createOrderReferenceForId($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'CreateOrderReferenceForId';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'id' 			=> 'Id',
            'id_type' 			=> 'IdType',
            'inherit_shipping_address' 	=> 'InheritShippingAddress',
            'confirm_now' 		=> 'ConfirmNow',
            'amount' 			=> 'OrderReferenceAttributes.OrderTotal.Amount',
            'currency_code' 		=> 'OrderReferenceAttributes.OrderTotal.CurrencyCode',
            'platform_id' 		=> 'OrderReferenceAttributes.PlatformId',
            'seller_note' 		=> 'OrderReferenceAttributes.SellerNote',
            'seller_order_id' 		=> 'OrderReferenceAttributes.SellerOrderAttributes.SellerOrderId',
            'store_name' 		=> 'OrderReferenceAttributes.SellerOrderAttributes.StoreName',
            'custom_information' 	=> 'OrderReferenceAttributes.SellerOrderAttributes.CustomInformation',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* GetBillingAgreementDetails API Call - Returns details about the Billing Agreement object and its current state.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_GetBillingAgreementDetails.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_billing_agreement_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getBillingAgreementDetails($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'GetBillingAgreementDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		  => 'SellerId',
            'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
            'address_consent_token' 	  => 'AddressConsentToken',
            'mws_auth_token' 		  => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

		return ($responseObject);
    }

    /* SetBillingAgreementDetails API call - Sets Billing Agreement details such as a description of the agreement and other information about the seller.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_SetBillingAgreementDetails.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_billing_agreement_id'] - [String]
     * @param requestParameters['amount'] - [String]
     * @param requestParameters['currency_code'] - [String]
     * @optional requestParameters['platform_id'] - [String]
     * @optional requestParameters['seller_note'] - [String]
     * @optional requestParameters['seller_billing_agreement_id'] - [String]
     * @optional requestParameters['store_name'] - [String]
     * @optional requestParameters['custom_information'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function setBillingAgreementDetails($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'SetBillingAgreementDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		  => 'SellerId',
            'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
            'platform_id' 		  => 'BillingAgreementAttributes.PlatformId',
            'seller_note' 		  => 'BillingAgreementAttributes.SellerNote',
            'seller_billing_agreement_id' => 'BillingAgreementAttributes.SellerBillingAgreementAttributes.SellerBillingAgreementId',
            'custom_information' 	  => 'BillingAgreementAttributes.SellerBillingAgreementAttributes.CustomInformation',
            'store_name' 		  => 'BillingAgreementAttributes.SellerBillingAgreementAttributes.StoreName',
            'mws_auth_token' 		  => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* ConfirmBillingAgreement API Call - Confirms that the Billing Agreement is free of constraints and all required information has been set on the Billing Agreement.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_ConfirmBillingAgreement.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_billing_agreement_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function confirmBillingAgreement($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'ConfirmBillingAgreement';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		  => 'SellerId',
            'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
            'mws_auth_token' 		  => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* ValidateBillignAgreement API Call - Validates the status of the Billing Agreement object and the payment method associated with it.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_ValidateBillingAgreement.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_billing_agreement_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function validateBillingAgreement($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'ValidateBillingAgreement';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		  => 'SellerId',
            'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
            'mws_auth_token' 		  => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* AuthorizeOnBillingAgreement API call - Reserves a specified amount against the payment method(s) stored in the Billing Agreement.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_AuthorizeOnBillingAgreement.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_billing_agreement_id'] - [String]
     * @param requestParameters['authorization_reference_id'] [String]
     * @param requestParameters['authorization_amount'] [String]
     * @param requestParameters['currency_code'] - [String]
     * @optional requestParameters['seller_authorization_note'] [String]
     * @optional requestParameters['transaction_timeout'] - Defaults to 1440 minutes
     * @optional requestParameters['capture_now'] [String]
     * @optional requestParameters['soft_descriptor'] - - [String]
     * @optional requestParameters['seller_note'] - [String]
     * @optional requestParameters['platform_id'] - [String]
     * @optional requestParameters['custom_information'] - [String]
     * @optional requestParameters['seller_order_id'] - [String]
     * @optional requestParameters['store_name'] - [String]
     * @optional requestParameters['inherit_shipping_address'] [Boolean] - Defaults to true
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function authorizeOnBillingAgreement($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'AuthorizeOnBillingAgreement';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 			=> 'SellerId',
            'amazon_billing_agreement_id' 	=> 'AmazonBillingAgreementId',
            'authorization_reference_id' 	=> 'AuthorizationReferenceId',
            'authorization_amount' 		=> 'AuthorizationAmount.Amount',
            'currency_code' 			=> 'AuthorizationAmount.CurrencyCode',
            'seller_authorization_note' 	=> 'SellerAuthorizationNote',
            'transaction_timeout' 		=> 'TransactionTimeout',
            'capture_now' 			=> 'CaptureNow',
            'soft_descriptor' 			=> 'SoftDescriptor',
            'seller_note' 			=> 'SellerNote',
            'platform_id' 			=> 'PlatformId',
            'custom_information' 		=> 'SellerOrderAttributes.CustomInformation',
            'seller_order_id' 			=> 'SellerOrderAttributes.SellerOrderId',
            'store_name' 			=> 'SellerOrderAttributes.StoreName',
            'inherit_shipping_address' 		=> 'InheritShippingAddress',
            'mws_auth_token' 			=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

		return ($responseObject);
    }

    /* CloseBillingAgreement API Call - Returns details about the Billing Agreement object and its current state.
     * @see http://docs.developer.amazonservices.com/en_US/off_amazon_payments/OffAmazonPayments_CloseBillingAgreement.html
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_billing_agreement_id'] - [String]
     * @optional requestParameters['closure_reason'] [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function closeBillingAgreement($requestParameters = array())
    {
        $parameters           = array();
        $parameters['Action'] = 'CloseBillingAgreement';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		  => 'SellerId',
            'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
            'closure_reason' 		  => 'ClosureReason',
            'mws_auth_token' 		  => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* charge convenience method
     * Performs the API calls
     * 1. SetOrderReferenceDetails / SetBillingAgreementDetails
     * 2. ConfirmOrderReference / ConfirmBillingAgreement
     * 3. Authorize (with Capture) / AuthorizeOnBillingAgreeemnt (with Capture)
     *
     * @param requestParameters['merchant_id'] - [String]
     *
     * @param requestParameters['amazon_reference_id'] - [String] : Order Reference ID /Billing Agreement ID
     * If requestParameters['amazon_reference_id'] is empty then the following is required,
     * @param requestParameters['amazon_order_reference_id'] - [String] : Order Reference ID
     * or,
     * @param requestParameters['amazon_billing_agreement_id'] - [String] : Billing Agreement ID
     * 
     * @param $requestParameters['charge_amount'] - [String] : Amount value to be captured
     * @param requestParameters['currency_code'] - [String] : Currency Code for the Amount
     * @param requestParameters['authorization_reference_id'] - [String]- Any unique string that needs to be passed
     * @optional requestParameters['charge_note'] - [String] : Seller Note sent to the buyer
     * @optional requestParameters['transaction_timeout'] - [String] : Defaults to 1440 minutes
     * @optional requestParameters['charge_order_id'] - [String] : Custom Order ID provided
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function charge($requestParameters = array()) {

		$requestParameters = array_change_key_case($requestParameters, CASE_LOWER);
		$requestParameters= ArrayUtil::trimArray($requestParameters);

		$setParameters = $authorizeParameters = $confirmParameters = $requestParameters;

		$chargeType = '';

		if (!empty($requestParameters['amazon_order_reference_id']))
		{
			$chargeType = 'OrderReference';

		} elseif(!empty($requestParameters['amazon_billing_agreement_id'])) {
			$chargeType = 'BillingAgreement';

		} elseif (!empty($requestParameters['amazon_reference_id'])) {
			switch (substr(strtoupper($requestParameters['amazon_reference_id']), 0, 1)) {
				case 'P':
				case 'S':
					$chargeType = 'OrderReference';
					$setParameters['amazon_order_reference_id'] = $requestParameters['amazon_reference_id'];
					$authorizeParameters['amazon_order_reference_id'] = $requestParameters['amazon_reference_id'];
					$confirmParameters['amazon_order_reference_id'] = $requestParameters['amazon_reference_id'];
					break;
				case 'B':
				case 'C':
					$chargeType = 'BillingAgreement';
					$setParameters['amazon_billing_agreement_id'] = $requestParameters['amazon_reference_id'];
					$authorizeParameters['amazon_billing_agreement_id'] = $requestParameters['amazon_reference_id'];
					$confirmParameters['amazon_billing_agreement_id'] = $requestParameters['amazon_reference_id'];
					break;
				default:
					throw new \Exception('Invalid Amazon Reference ID');
			}
		} else {
			throw new \Exception('key amazon_order_reference_id or amazon_billing_agreement_id is null and is a required parameter');
		}

		// Set the other parameters if the values are present
		$setParameters['amount'] = !empty($requestParameters['charge_amount']) ? $requestParameters['charge_amount'] : '';
		$authorizeParameters['authorization_amount'] = !empty($requestParameters['charge_amount']) ? $requestParameters['charge_amount'] : '';

		$setParameters['seller_note'] = !empty($requestParameters['charge_note']) ? $requestParameters['charge_note'] : '';
		$authorizeParameters['seller_authorization_note'] = !empty($requestParameters['charge_note']) ? $requestParameters['charge_note'] : '';
		$authorizeParameters['seller_note'] = !empty($requestParameters['charge_note']) ? $requestParameters['charge_note'] : '';

		$setParameters['seller_order_id'] = !empty($requestParameters['charge_order_id']) ? $requestParameters['charge_order_id'] : '';
		$setParameters['seller_billing_agreement_id'] = !empty($requestParameters['charge_order_id']) ? $requestParameters['charge_order_id'] : '';
		$authorizeParameters['seller_order_id'] = !empty($requestParameters['charge_order_id']) ? $requestParameters['charge_order_id'] : '';

		$authorizeParameters['capture_now'] = !empty($requestParameters['capture_now']) ? $requestParameters['capture_now'] : false;

		$response = $this->makeChargeCalls($chargeType, $setParameters, $confirmParameters, $authorizeParameters);
		return $response;
    }

    /* makeChargeCalls - makes API calls based off the charge type (OrderReference or BillingAgreement) */

    private function makeChargeCalls($chargeType, $setParameters, $confirmParameters, $authorizeParameters)
    {
		switch ($chargeType) {
            
			case 'OrderReference':

				// Get the Order Reference details and feed the response object to the ResponseParser
                $responseObj = $this->getOrderReferenceDetails($setParameters);
		
				// Call the function getOrderReferenceDetailsStatus in ResponseParser.php providing it the XML response
                // $oroStatus is an array containing the State of the Order Reference ID
                $oroStatus = $responseObj->getOrderReferenceDetailsStatus($responseObj->toXml());
		
				if ($oroStatus['State'] === 'Draft') {
					$response = $this->setOrderReferenceDetails($setParameters);
					if ($this->success) {
							$this->confirmOrderReference($confirmParameters);
					}
				}
		
                $responseObj = $this->getOrderReferenceDetails($setParameters);
		
				// Check the Order Reference Status again before making the Authorization.
                $oroStatus = $responseObj->getOrderReferenceDetailsStatus($responseObj->toXml());
		
				if ($oroStatus['State'] === 'Open') {
					if ($this->success) {
							$response = $this->Authorize($authorizeParameters);
					}
				}
				if ($oroStatus['State'] != 'Open' && $oroStatus['State'] != 'Draft') {
					throw new \Exception('The Order Reference is in the ' . $oroStatus['State'] . " State. It should be in the Draft or Open State");
				}

				return $response;

			case 'BillingAgreement':

				// Get the Billing Agreement details and feed the response object to the ResponseParser

				$responseObj = $this->getBillingAgreementDetails($setParameters);

				// Call the function getBillingAgreementDetailsStatus in ResponseParser.php providing it the XML response
                // $baStatus is an array containing the State of the Billing Agreement
                $baStatus = $responseObj->getBillingAgreementDetailsStatus($responseObj->toXml());
                
				if ($baStatus['State'] === 'Draft') {
                    $response = $this->setBillingAgreementDetails($setParameters);
                    if ($this->success) {
                        $response = $this->confirmBillingAgreement($confirmParameters);
                    }
                }
                
				// Check the Billing Agreement status again before making the Authorization.
                $responseObj = $this->getBillingAgreementDetails($setParameters);
                $baStatus = $responseObj->getBillingAgreementDetailsStatus($responseObj->toXml());
		
                if ($this->success && $baStatus['State'] === 'Open') {
                    $response = $this->authorizeOnBillingAgreement($authorizeParameters);
                }
		
				if($baStatus['State'] != 'Open' && $baStatus['State'] != 'Draft') {
					throw new \Exception('The Billing Agreement is in the ' . $baStatus['State'] . " State. It should be in the Draft or Open State");
				}
		
				return $response;
	    }
	}

    /* GetProviderCreditDetails API Call - Get the details of the Provider Credit.
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_provider_credit_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getProviderCreditDetails($requestParameters = array())
    {
		$parameters           = array();
        $parameters['Action'] = 'GetProviderCreditDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		=> 'SellerId',
            'amazon_provider_credit_id' => 'AmazonProviderCreditId',
            'mws_auth_token' 		=> 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* GetProviderCreditReversalDetails API Call - Get details of the Provider Credit Reversal.
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_provider_credit_reversal_id'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function getProviderCreditReversalDetails($requestParameters = array())
    {
		$parameters           = array();
        $parameters['Action'] = 'GetProviderCreditReversalDetails';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		  	 => 'SellerId',
            'amazon_provider_credit_reversal_id' => 'AmazonProviderCreditReversalId',
            'mws_auth_token' 		  	 => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* ReverseProviderCredit API Call - Reverse the Provider Credit.
     *
     * @param requestParameters['merchant_id'] - [String]
     * @param requestParameters['amazon_provider_credit_id'] - [String]
     * @optional requestParameters['credit_reversal_reference_id'] - [String]
     * @param requestParameters['credit_reversal_amount'] - [String]
     * @optional requestParameters['currency_code'] - [String]
     * @optional requestParameters['credit_reversal_note'] - [String]
     * @optional requestParameters['mws_auth_token'] - [String]
     */

    public function reverseProviderCredit($requestParameters = array())
    {
		$parameters           = array();
        $parameters['Action'] = 'ReverseProviderCredit';
        $requestParameters    = array_change_key_case($requestParameters, CASE_LOWER);

        $fieldMappings = array(
            'merchant_id' 		   => 'SellerId',
            'amazon_provider_credit_id'    => 'AmazonProviderCreditId',
			'credit_reversal_reference_id' => 'CreditReversalReferenceId',
			'credit_reversal_amount' 	   => 'CreditReversalAmount.Amount',
			'currency_code' 		   => 'CreditReversalAmount.CurrencyCode',
			'credit_reversal_note' 	   => 'CreditReversalNote',
            'mws_auth_token' 		   => 'MWSAuthToken'
        );

        $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

        return ($responseObject);
    }

    /* Based on the config['region'] and config['sandbox'] values get the user profile URL */

    private function profileEndpointUrl()
    {
		$profileEnvt = strtolower($this->config['sandbox']) ? "api.sandbox" : "api";
	
        if (!empty($this->config['region'])) {
            $region = strtolower($this->config['region']);

			if (array_key_exists($region, $this->regionMappings) ) {
					$this->profileEndpoint = 'https://' . $profileEnvt . '.' . $this->profileEndpointUrls[$region];
			} else {
				throw new \Exception($region . ' is not a valid region');
			}
		} else {
            throw new \Exception("config['region'] is a required parameter and is not set");
        }
    }

    protected function setModePath()
    {
        $this->modePath = strtolower($this->config['sandbox']) ? 'OffAmazonPayments_Sandbox' : 'OffAmazonPayments';
    }
}
