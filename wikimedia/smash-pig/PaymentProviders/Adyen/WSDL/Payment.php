<?php namespace SmashPig\PaymentProviders\Adyen\WSDL;

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Context;

class BalanceCheckRequest {
	public $additionalAmount; // Amount
	public $additionalData; // anyType2anyTypeMap
	public $amount; // Amount
	public $bankAccount; // BankAccount
	public $browserInfo; // BrowserInfo
	public $card; // Card
	public $dccQuote; // ForexQuote
	public $deliveryAddress; // Address
	public $elv; // ELV
	public $fraudOffset; // int
	public $merchantAccount; // string
	public $mpiData; // ThreeDSecureData
	public $orderReference; // string
	public $recurring; // Recurring
	public $reference; // string
	public $selectedBrand; // string
	public $selectedRecurringDetailReference; // string
	public $sessionId; // string
	public $shopperEmail; // string
	public $shopperIP; // string
	public $shopperInteraction; // string
	public $shopperReference; // string
	public $shopperStatement; // string
}

class BalanceCheckResult {
	public $additionalData; // anyType2anyTypeMap
	public $currentBalance; // Amount
	public $pspReference; // string
	public $responseCode; // BalanceCheckResponseCode
}

class BalanceCheckResponseCode {
	const OK = 'OK';
	const NO_BALANCE = 'NO_BALANCE';
	const NOT_CHECKED = 'NOT_CHECKED';
	const NOT_ALLOWED = 'NOT_ALLOWED';
}

class BankAccount {
	public $bankAccountNumber; // string
	public $bankLocationId; // string
	public $bankName; // string
	public $bic; // string
	public $countryCode; // string
	public $iban; // string
	public $ownerName; // string
}

class Card {
	public $billingAddress; // Address
	public $brand; // string
	public $cvc; // cvc
	public $expiryMonth; // expiryMonth
	public $expiryYear; // expiryYear
	public $holderName; // holderName
	public $issueNumber; // issueNumber
	public $number; // number
	public $startMonth; // startMonth
	public $startYear; // startYear
}

class cvc {
}

class expiryMonth {
}

class expiryYear {
}

class holderName {
}

class issueNumber {
}

class number {
}

class startMonth {
}

class startYear {
}

class DirectDebitRequest {
	public $additionalAmount; // Amount
	public $additionalData; // anyType2anyTypeMap
	public $amount; // Amount
	public $bankAccount; // BankAccount
	public $browserInfo; // BrowserInfo
	public $dccQuote; // ForexQuote
	public $deliveryAddress; // Address
	public $fraudOffset; // int
	public $merchantAccount; // string
	public $orderReference; // string
	public $recurring; // Recurring
	public $reference; // string
	public $selectedBrand; // string
	public $selectedRecurringDetailReference; // string
	public $sessionId; // string
	public $shopperEmail; // string
	public $shopperIP; // string
	public $shopperInteraction; // string
	public $shopperReference; // string
	public $shopperStatement; // string
}

class DirectDebitResponse {
	public $additionalData; // anyType2anyTypeMap
	public $fraudResult; // FraudResult
	public $pspReference; // string
	public $refusalReason; // string
	public $resultCode; // string
}

class ELV {
	public $accountHolderName; // string
	public $bankAccountNumber; // string
	public $bankLocation; // string
	public $bankLocationId; // string
	public $bankName; // string
}

class ForexQuote {
	public $account; // string
	public $accountType; // string
	public $baseAmount; // Amount
	public $basePoints; // int
	public $buy; // Amount
	public $interbank; // Amount
	public $reference; // string
	public $sell; // Amount
	public $signature; // string
	public $source; // string
	public $type; // string
	public $validTill; // dateTime
}

class FraudCheckResult {
	public $accountScore; // int
	public $checkId; // int
	public $name; // string
}

class FraudResult {
	public $accountScore; // int
	public $results; // ArrayOfFraudCheckResult
}

class FundTransferRequest {
	public $additionalData; // anyType2anyTypeMap
	public $authorisationCode; // string
	public $merchantAccount; // string
	public $modificationAmount; // Amount
	public $originalReference; // string
	public $reference; // string
	public $shopperEmail; // string
	public $shopperStatement; // string
}

class FundTransferResult {
	public $additionalData; // anyType2anyTypeMap
	public $pspReference; // string
	public $response; // string
}

class ModificationRequest {
	public $additionalData; // anyType2anyTypeMap
	public $authorisationCode; // string
	public $merchantAccount; // string
	public $modificationAmount; // Amount
	public $originalReference; // string
}

class ModificationResult {
	public $additionalData; // anyType2anyTypeMap
	public $pspReference; // string
	public $response; // string
}

class PaymentRequest {
	public $additionalAmount; // Amount
	public $additionalData; // anyType2anyTypeMap
	public $amount; // Amount
	public $bankAccount; // BankAccount
	public $browserInfo; // BrowserInfo
	public $card; // Card
	public $dccQuote; // ForexQuote
	public $deliveryAddress; // Address
	public $elv; // ELV
	public $fraudOffset; // int
	public $merchantAccount; // string
	public $mpiData; // ThreeDSecureData
	public $orderReference; // string
	public $recurring; // Recurring
	public $reference; // string
	public $selectedBrand; // string
	public $selectedRecurringDetailReference; // string
	public $sessionId; // string
	public $shopperEmail; // string
	public $shopperIP; // string
	public $shopperInteraction; // string
	public $shopperReference; // string
	public $shopperStatement; // string
}

class PaymentRequest3d {
	public $additionalAmount; // Amount
	public $additionalData; // anyType2anyTypeMap
	public $amount; // Amount
	public $browserInfo; // BrowserInfo
	public $dccQuote; // ForexQuote
	public $deliveryAddress; // Address
	public $fraudOffset; // int
	public $md; // string
	public $merchantAccount; // string
	public $orderReference; // string
	public $paResponse; // string
	public $recurring; // Recurring
	public $reference; // string
	public $selectedBrand; // string
	public $selectedRecurringDetailReference; // string
	public $sessionId; // string
	public $shopperEmail; // string
	public $shopperIP; // string
	public $shopperInteraction; // string
	public $shopperReference; // string
	public $shopperStatement; // string
}

class PaymentResult {
	public $additionalData; // anyType2anyTypeMap
	public $authCode; // string
	public $dccAmount; // Amount
	public $dccSignature; // string
	public $fraudResult; // FraudResult
	public $issuerUrl; // string
	public $md; // string
	public $paRequest; // string
	public $pspReference; // string
	public $refusalReason; // string
	public $resultCode; // string
}

class Recurring {
	public $contract; // string
	public $recurringDetailName; // string
}

class ThreeDSecureData {
	public $authenticationResponse; // string
	public $cavv; // base64Binary
	public $cavvAlgorithm; // string
	public $directoryResponse; // string
	public $eci; // string
	public $xid; // base64Binary
}

class anyType2anyTypeMap {
	public $entry; // entry
}

class entry {
	public $key; // anyType
	public $value; // anyType
}

class authorise {
	public $paymentRequest; // PaymentRequest
}

class authorise3d {
	public $paymentRequest3d; // PaymentRequest3d
}

class authorise3dResponse {
	public $paymentResult; // PaymentResult
}

class authoriseReferral {
	public $modificationRequest; // ModificationRequest
}

class authoriseReferralResponse {
	public $authoriseReferralResult; // ModificationResult
}

class authoriseResponse {
	public $paymentResult; // PaymentResult
}

class balanceCheck {
	public $request; // BalanceCheckRequest
}

class balanceCheckResponse {
	public $response; // BalanceCheckResult
}

class cancel {
	public $modificationRequest; // ModificationRequest
}

class cancelOrRefund {
	public $modificationRequest; // ModificationRequest
}

class cancelOrRefundResponse {
	public $cancelOrRefundResult; // ModificationResult
}

class cancelResponse {
	public $cancelResult; // ModificationResult
}

class capture {
	public $modificationRequest; // ModificationRequest
}

class captureResponse {
	public $captureResult; // ModificationResult
}

class checkFraud {
	public $paymentRequest; // PaymentRequest
}

class checkFraudResponse {
	public $paymentResult; // PaymentResult
}

class directdebit {
	public $request; // DirectDebitRequest
}

class directdebitFuncResponse {
	public $response; // DirectDebitResponse
}

class fundTransfer {
	public $request; // FundTransferRequest
}

class fundTransferResponse {
	public $result; // FundTransferResult
}

class refund {
	public $modificationRequest; // ModificationRequest
}

class refundResponse {
	public $refundResult; // ModificationResult
}

class refundWithData {
	public $request; // PaymentRequest
}

class refundWithDataResponse {
	public $result; // PaymentResult
}

class Address {
	public $city; // string
	public $country; // string
	public $houseNumberOrName; // string
	public $postalCode; // string
	public $stateOrProvince; // string
	public $street; // string
}

class Amount {
	public $currency; // currency
	public $value; // long
}

class currency {
}

class BrowserInfo {
	public $acceptHeader; // acceptHeader
	public $userAgent; // userAgent
}

class acceptHeader {
}

class userAgent {
}

class ServiceException {
	public $error; // Error
	public $type; // Type
}

class Error {
	const Unknown = 'Unknown';
	const NotAllowed = 'NotAllowed';
	const NoAmountSpecified = 'NoAmountSpecified';
	const UnableToDetermineVariant = 'UnableToDetermineVariant';
	const InvalidMerchantAccount = 'InvalidMerchantAccount';
	const RequestMissing = 'RequestMissing';
	const InternalError = 'InternalError';
	const UnableToProcess = 'UnableToProcess';
	const PaymentDetailsAreNotSupported = 'PaymentDetailsAreNotSupported';
}

class Type {
	const internal = 'internal';
	const validation = 'validation';
	const security = 'security';
	const configuration = 'configuration';
}


/**
 * Payment class
 *
 *
 *
 * @author		{author}
 * @copyright {copyright}
 * @package	 {package}
 */
class Payment extends \SoapClient {

	private static $classmap = array(
		'BalanceCheckRequest' => 'BalanceCheckRequest',
		'BalanceCheckResult' => 'BalanceCheckResult',
		'BalanceCheckResponseCode' => 'BalanceCheckResponseCode',
		'BankAccount' => 'BankAccount',
		'Card' => 'Card',
		'cvc' => 'cvc',
		'expiryMonth' => 'expiryMonth',
		'expiryYear' => 'expiryYear',
		'holderName' => 'holderName',
		'issueNumber' => 'issueNumber',
		'number' => 'number',
		'startMonth' => 'startMonth',
		'startYear' => 'startYear',
		'DirectDebitRequest' => 'DirectDebitRequest',
		'DirectDebitResponse' => 'DirectDebitResponse',
		'ELV' => 'ELV',
		'ForexQuote' => 'ForexQuote',
		'FraudCheckResult' => 'FraudCheckResult',
		'FraudResult' => 'FraudResult',
		'FundTransferRequest' => 'FundTransferRequest',
		'FundTransferResult' => 'FundTransferResult',
		'ModificationRequest' => 'ModificationRequest',
		'ModificationResult' => 'ModificationResult',
		'PaymentRequest' => 'PaymentRequest',
		'PaymentRequest3d' => 'PaymentRequest3d',
		'PaymentResult' => 'PaymentResult',
		'Recurring' => 'Recurring',
		'ThreeDSecureData' => 'ThreeDSecureData',
		'anyType2anyTypeMap' => 'anyType2anyTypeMap',
		'entry' => 'entry',
		'authorise' => 'authorise',
		'authorise3d' => 'authorise3d',
		'authorise3dResponse' => 'authorise3dResponse',
		'authoriseReferral' => 'authoriseReferral',
		'authoriseReferralResponse' => 'authoriseReferralResponse',
		'authoriseResponse' => 'authoriseResponse',
		'balanceCheck' => 'balanceCheck',
		'balanceCheckResponse' => 'balanceCheckResponse',
		'cancel' => 'cancel',
		'cancelOrRefund' => 'cancelOrRefund',
		'cancelOrRefundResponse' => 'cancelOrRefundResponse',
		'cancelResponse' => 'cancelResponse',
		'capture' => 'capture',
		'captureResponse' => 'captureResponse',
		'checkFraud' => 'checkFraud',
		'checkFraudResponse' => 'checkFraudResponse',
		'directdebit' => 'directdebit',
		'directdebitFuncResponse' => 'directdebitResponse',
		'fundTransfer' => 'fundTransfer',
		'fundTransferResponse' => 'fundTransferResponse',
		'refund' => 'refund',
		'refundResponse' => 'refundResponse',
		'refundWithData' => 'refundWithData',
		'refundWithDataResponse' => 'refundWithDataResponse',
		'Address' => 'Address',
		'Amount' => 'Amount',
		'currency' => 'currency',
		'BrowserInfo' => 'BrowserInfo',
		'acceptHeader' => 'acceptHeader',
		'userAgent' => 'userAgent',
		'ServiceException' => 'ServiceException',
		'Error' => 'Error',
		'Type' => 'Type',
	);

	protected $retries;
	protected $uri = 'http://payment.services.adyen.com';

	public function __construct( $wsdl = "https://pal-live.adyen.com/pal/Payment.wsdl", $options = array() ) {
		$this->retries = Context::get()->getProviderConfiguration()->val( 'curl/retries' );
		foreach ( self::$classmap as $key => $value ) {
			if ( !isset( $options['classmap'][$key] ) ) {
				$options['classmap'][$key] = '\SmashPig\PaymentProviders\Adyen\WSDL\\' . $value;
			}
		}
		$options['connection_timeout'] = Context::get()->getProviderConfiguration()->val( 'curl/timeout' );
		$options['exceptions'] = true;
		parent::__construct( $wsdl, $options );
	}

	/**
	 *
	 *
	 * @param authorise $parameters
	 * @return authoriseResponse
	 */
	public function authorise( authorise $parameters ) {
		return $this->makeApiCall( 'authorise', $parameters );
	}

	/**
	 *
	 *
	 * @param authorise3d $parameters
	 * @return authorise3dResponse
	 */
	public function authorise3d( authorise3d $parameters ) {
		return $this->makeApiCall( 'authorise3d', $parameters );
	}

	/**
	 *
	 *
	 * @param authoriseReferral $parameters
	 * @return authoriseReferralResponse
	 */
	public function authoriseReferral( authoriseReferral $parameters ) {
		return $this->makeApiCall( 'authoriseReferral', $parameters );
	}

	/**
	 *
	 *
	 * @param balanceCheck $parameters
	 * @return balanceCheckResponse
	 */
	public function balanceCheck( balanceCheck $parameters ) {
		return $this->makeApiCall( 'balanceCheck', $parameters );
	}

	/**
	 *
	 *
	 * @param cancel $parameters
	 * @return cancelResponse
	 */
	public function cancel( cancel $parameters ) {
		return $this->makeApiCall( 'cancel', $parameters );
	}

	/**
	 *
	 *
	 * @param cancelOrRefund $parameters
	 * @return cancelOrRefundResponse
	 */
	public function cancelOrRefund( cancelOrRefund $parameters ) {
		return $this->makeApiCall( 'cancelOrRefund', $parameters );
	}

	/**
	 *
	 *
	 * @param capture $parameters
	 * @return captureResponse
	 */
	public function capture( capture $parameters ) {
		return $this->makeApiCall( 'capture', $parameters );
	}

	/**
	 *
	 *
	 * @param checkFraud $parameters
	 * @return checkFraudResponse
	 */
	public function checkFraud( checkFraud $parameters ) {
		return $this->makeApiCall( 'checkFraud', $parameters );
	}

	/**
	 *
	 *
	 * @param directdebit $parameters
	 * @return directdebitFuncResponse
	 */
	public function directdebit( directdebit $parameters ) {
		return $this->makeApiCall( 'directdebit', $parameters );
	}

	/**
	 *
	 *
	 * @param fundTransfer $parameters
	 * @return fundTransferResponse
	 */
	public function fundTransfer( fundTransfer $parameters ) {
		return $this->makeApiCall( 'fundTransfer', $parameters );
	}

	/**
	 *
	 *
	 * @param refund $parameters
	 * @return refundResponse
	 */
	public function refund( refund $parameters ) {
		return $this->makeApiCall( 'refund', $parameters );
	}

	/**
	 *
	 *
	 * @param refundWithData $parameters
	 * @return refundWithDataResponse
	 */
	public function refundWithData( refundWithData $parameters ) {
		return $this->makeApiCall( 'refundWithData', $parameters );
	}

	protected function makeApiCall ( $path, $parameters ) {
		$count = 0;
		while ( $count < $this->retries ) {
			try {
				return $this->__soapCall(
					$path,
					array( $parameters ),
					array( 'uri' => $this->uri, 'soapaction' => '' )
				);
			} catch ( \SoapFault $e ) {
				$count += 1;
				if ( $count == $this->retries ) {
					throw $e;
				}
				Logger::warning(
					"Exception caught in Soap call $path: {$e->getMessage()}", $e->getTrace(), $e
				);
			}
		}
	}

}
