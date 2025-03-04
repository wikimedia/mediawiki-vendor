<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\ApiException;
use SmashPig\PaymentData\ErrorCode;

class ExceptionMapper {
	/**
	 * @var array
	 * List gleaned from https://docs.adyen.com/development-resources/error-codes
	 * then roughly classified with a few regexes and some hand-coding
	 */
	protected static $fatalErrorCodes = [
		'000' => ErrorCode::UNKNOWN, // Unknown
		'010' => ErrorCode::UNKNOWN, // Not allowed
		'100' => ErrorCode::MISSING_REQUIRED_DATA, // Required object 'amount' is not provided.
		'104' => ErrorCode::UNKNOWN, // Billing address problem
		'105' => ErrorCode::UNEXPECTED_VALUE, // Invalid paRes from issuer
		'106' => ErrorCode::UNKNOWN, // This session was already used previously
		'107' => ErrorCode::UNKNOWN, // Recurring is not enabled
		'108' => ErrorCode::UNEXPECTED_VALUE, // Invalid bankaccount number
		'109' => ErrorCode::UNEXPECTED_VALUE, // Invalid variant
		'110' => ErrorCode::MISSING_REQUIRED_DATA, // BankDetails missing
		'111' => ErrorCode::UNEXPECTED_VALUE, // Invalid BankCountryCode specified
		'112' => ErrorCode::UNKNOWN, // This bank country is not supported
		'113' => ErrorCode::MISSING_REQUIRED_DATA, // No InvoiceLines provided
		'114' => ErrorCode::UNKNOWN, // Received an incorrect InvoiceLine
		'115' => ErrorCode::UNKNOWN, // Total amount is not the same as the sum of the lines
		'116' => ErrorCode::UNEXPECTED_VALUE, // Invalid date of birth
		'117' => ErrorCode::UNEXPECTED_VALUE, // Invalid billing address
		'118' => ErrorCode::UNEXPECTED_VALUE, // Invalid delivery address
		'119' => ErrorCode::UNEXPECTED_VALUE, // Invalid shopper name
		'120' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'shopperEmail' is not provided.
		'121' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'shopperReference' is not provided.
		'122' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'telephoneNumber' is not provided.
		'124' => ErrorCode::UNEXPECTED_VALUE, // Invalid PhoneNumber
		'125' => ErrorCode::UNEXPECTED_VALUE, // Invalid recurring contract specified
		'126' => ErrorCode::UNKNOWN, // Bank Account or Bank Location Id not valid or missing
		'127' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'accountHolderName' is not provided.
		'128' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'card.holderName' is not provided.
		'129' => ErrorCode::UNEXPECTED_VALUE, // Expiry Date Invalid
		'130' => ErrorCode::MISSING_REQUIRED_DATA, // Reference Missing
		'131' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'billingAddress.city' is not provided.
		'132' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'billingAddress.street' is not provided.
		'133' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'billingAddress.houseNumberOrName' is not provided.
		'134' => ErrorCode::UNKNOWN, // Billing address problem (Country)
		'135' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'billingAddress.stateOrProvince' is not provided.
		'136' => ErrorCode::UNKNOWN, // Failed to retrieve OpenInvoiceLines
		'137' => ErrorCode::UNKNOWN, // Field 'amount' is not valid.
		'138' => ErrorCode::UNKNOWN, // Unsupported currency specified
		'139' => ErrorCode::UNKNOWN, // Recurring requires shopperEmail and shopperReference
		'140' => ErrorCode::UNEXPECTED_VALUE, // Invalid expiryMonth[1..12] / expiryYear[>2000], or before now
		'141' => ErrorCode::UNEXPECTED_VALUE, // Invalid expiryMonth[1..12] / expiryYear[>2000]
		'142' => ErrorCode::UNKNOWN, // Bank Name or Bank Location not valid or missing
		'143' => ErrorCode::UNKNOWN, // Submitted total iDEAL merchantReturnUrl length is {0}, but max size is {1} for this request
		'144' => ErrorCode::UNEXPECTED_VALUE, // Invalid startMonth[1..12] / startYear[>2000], or in the future
		'145' => ErrorCode::UNEXPECTED_VALUE, // Invalid issuer countrycode
		'146' => ErrorCode::UNEXPECTED_VALUE, // Invalid social security number
		'147' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'deliveryAddress.city' is not provided.
		'148' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'deliveryAddress.street' is not provided.
		'149' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'deliveryAddress.houseNumberOrName' is not provided.
		'150' => ErrorCode::UNKNOWN, // Delivery address problem (Country)
		'151' => ErrorCode::MISSING_REQUIRED_DATA, // Required field 'deliveryAddress.stateOrProvince' is not provided.
		'152' => ErrorCode::UNEXPECTED_VALUE, // Invalid number of installments
		'153' => ErrorCode::UNEXPECTED_VALUE, // Invalid CVC
		'154' => ErrorCode::MISSING_REQUIRED_DATA, // No additional data specified
		'155' => ErrorCode::MISSING_REQUIRED_DATA, // No acquirer specified
		'156' => ErrorCode::MISSING_REQUIRED_DATA, // No authorisation mid specified
		'157' => ErrorCode::MISSING_REQUIRED_DATA, // No fields specified
		'158' => ErrorCode::MISSING_REQUIRED_DATA, // Required field {0} not specified
		'159' => ErrorCode::UNEXPECTED_VALUE, // Invalid number of requests
		'160' => ErrorCode::UNKNOWN, // Not allowed to store Payout Details
		'161' => ErrorCode::UNEXPECTED_VALUE, // Invalid iban
		'162' => ErrorCode::UNEXPECTED_VALUE, // Inconsistent iban
		'163' => ErrorCode::UNEXPECTED_VALUE, // Invalid bic
		'164' => ErrorCode::UNKNOWN, // Auto capture delay invalid or out of range
		'165' => ErrorCode::UNKNOWN, // MandateId does not match pattern
		'166' => ErrorCode::UNKNOWN, // Amount not allowed for this operation
		'167' => ErrorCode::MISSING_TRANSACTION_ID, // Original pspReference required for this operation
		'168' => ErrorCode::MISSING_REQUIRED_DATA, // AuthorisationCode required for this operation
		'170' => ErrorCode::MISSING_REQUIRED_DATA, // Generation Date required but missing
		'171' => ErrorCode::UNKNOWN, // Unable to parse Generation Date
		'172' => ErrorCode::UNKNOWN, // Encrypted data used outside of valid time period
		'173' => ErrorCode::UNKNOWN, // Unable to load Private Key for decryption
		'174' => ErrorCode::UNKNOWN, // Unable to decrypt data
		'175' => ErrorCode::UNKNOWN, // Unable to parse JSON data
		'180' => ErrorCode::UNEXPECTED_VALUE, // Invalid shopperReference
		'181' => ErrorCode::UNEXPECTED_VALUE, // Invalid shopperEmail
		'182' => ErrorCode::UNEXPECTED_VALUE, // Invalid selected brand
		'183' => ErrorCode::UNEXPECTED_VALUE, // Invalid recurring contract
		'184' => ErrorCode::UNEXPECTED_VALUE, // Invalid recurring detail name
		'185' => ErrorCode::UNEXPECTED_VALUE, // Invalid additionalData
		'186' => ErrorCode::MISSING_REQUIRED_DATA, // Missing additionalData field
		'187' => ErrorCode::UNEXPECTED_VALUE, // Invalid additionalData field
		'188' => ErrorCode::UNEXPECTED_VALUE, // Invalid pspEchoData
		'189' => ErrorCode::UNEXPECTED_VALUE, // Invalid shopperStatement
		'190' => ErrorCode::UNEXPECTED_VALUE, // Invalid shopper IP
		'191' => ErrorCode::MISSING_REQUIRED_DATA, // No params specified
		'192' => ErrorCode::UNEXPECTED_VALUE, // Invalid field {0}
		'193' => ErrorCode::UNKNOWN, // Bin Details not found for the given card number
		'194' => ErrorCode::MISSING_REQUIRED_DATA, // Billing address missing
		'195' => ErrorCode::UNKNOWN, // Could not find an account with this key: {0}
		'196' => ErrorCode::UNEXPECTED_VALUE, // Invalid Mcc
		'198' => ErrorCode::UNKNOWN, // Reference may not exceed 79 characters
		'199' => ErrorCode::UNKNOWN, // The cryptographic operation could not proceed, no key configured
		'200' => ErrorCode::UNEXPECTED_VALUE, // Invalid country code
		'203' => ErrorCode::UNEXPECTED_VALUE, // Invalid Bank account holder name
		'205' => ErrorCode::MISSING_REQUIRED_DATA, // Missing or invalid networkTxReference
		'217' => ErrorCode::MISSING_REQUIRED_DATA, // Field 'shopperInteraction' is missing or not valid.
		'218' => ErrorCode::UNKNOWN, // Field 'shopperInteraction' is missing or not valid.
		'600' => ErrorCode::MISSING_REQUIRED_DATA, // No InvoiceProject provided
		'601' => ErrorCode::MISSING_REQUIRED_DATA, // No InvoiceBatch provided
		'602' => ErrorCode::UNKNOWN, // No creditorAccount specified
		'603' => ErrorCode::UNKNOWN, // No projectCode specified
		'604' => ErrorCode::UNKNOWN, // No creditorAccount found
		'605' => ErrorCode::UNKNOWN, // No project found
		'606' => ErrorCode::UNKNOWN, // Unable to create InvoiceProject
		'607' => ErrorCode::UNKNOWN, // InvoiceBatch already exists
		'608' => ErrorCode::UNKNOWN, // Unable to create InvoiceBatch
		'609' => ErrorCode::UNKNOWN, // InvoiceBatch validity period exceeded
		'690' => ErrorCode::UNKNOWN, // Error while storing debtor
		'691' => ErrorCode::UNKNOWN, // Error while storing invoice
		'692' => ErrorCode::UNKNOWN, // Error while checking if invoice already exists for creditorAccount
		'693' => ErrorCode::UNKNOWN, // Error while searching invoices
		'694' => ErrorCode::UNKNOWN, // No Invoice Configuration configured for creditAccount
		'695' => ErrorCode::UNEXPECTED_VALUE, // Invalid Invoice Configuration configured for creditAccount
		'700' => ErrorCode::MISSING_REQUIRED_DATA, // No method specified
		'701' => ErrorCode::UNKNOWN, // Server could not process request
		'702' => ErrorCode::UNKNOWN, // Problem parsing request
		'703' => ErrorCode::UNKNOWN, // Required resource temporarily unavailable
		'704' => ErrorCode::UNKNOWN, // Request already processed
		'800' => ErrorCode::UNKNOWN, // Contract not found
		'801' => ErrorCode::UNKNOWN, // Too many PaymentDetails defined
		'802' => ErrorCode::UNEXPECTED_VALUE, // Invalid contract
		'803' => ErrorCode::UNKNOWN, // PaymentDetail not found
		'804' => ErrorCode::UNKNOWN, // Failed to disable
		'805' => ErrorCode::UNKNOWN, // RecurringDetailReference not available for provided recurring-contract
		'806' => ErrorCode::UNKNOWN, // No applicable contractTypes left for this payment-method
		'807' => ErrorCode::UNEXPECTED_VALUE, // Invalid combination of shopper interaction and recurring-contract
		'820' => ErrorCode::MISSING_REQUIRED_DATA, // CVC is required for OneClick card payments.
		'901' => ErrorCode::UNEXPECTED_VALUE, // Invalid Merchant Account
		'902' => ErrorCode::UNEXPECTED_VALUE, // Invalid or empty request data
		'903' => ErrorCode::UNKNOWN, // Internal error
		'904' => ErrorCode::UNKNOWN, // Unable To Process
		'905' => ErrorCode::UNEXPECTED_VALUE, // Payment details are not supported
		'905_2' => ErrorCode::ACCOUNT_MISCONFIGURATION, // No specified acquirer account found for '{0}' for specified acquirer '{1}' with variant '{2}' for unit '{3}' for Action '{4}'
		'905_4' => ErrorCode::ACCOUNT_MISCONFIGURATION, // Cashback and Cashout are not allowed for the configured acquirer account.
		'905_5' => ErrorCode::ACCOUNT_MISCONFIGURATION, // No acquirer account active and configured for Klarna.
		'905_6' => ErrorCode::ACCOUNT_MISCONFIGURATION, // Both 'KlarnaPayments' and 'Klarna' platforms have been configured for the merchant account. Only one of two is allowed.
		'906' => ErrorCode::UNEXPECTED_VALUE, // Invalid Request: Original pspReference is invalid for this environment
		'907' => ErrorCode::UNKNOWN, // Payment details are not supported for this country/ MCC combination
		'908' => ErrorCode::UNEXPECTED_VALUE, // Invalid request
		'912' => ErrorCode::UNKNOWN, // The TX Variant does not support the redemption type
		'916' => ErrorCode::UNKNOWN, // The transaction amount exceeds the allowed limit for this type of card
		'918' => ErrorCode::MISSING_REQUIRED_DATA, // Required object 'card' is not provided.
		'919' => ErrorCode::UNEXPECTED_VALUE, // The 'additionalAmount' currency should be the same currency as the payment.
		'920' => ErrorCode::UNEXPECTED_VALUE, // Total payment amount should be equal or greater than zero.
		'921_1' => ErrorCode::MISSING_REQUIRED_DATA, // Object 'mpiData' is missing, required due to threeDReference.
		'921_2' => ErrorCode::UNEXPECTED_VALUE, // Object 'mpiData' is not valid. Reason: 'authenticationResponse' missing.
		'922' => ErrorCode::UNEXPECTED_VALUE, // Account updater connection only supported for Visa & MasterCard.
		'923' => ErrorCode::ACCOUNT_MISCONFIGURATION, // No registered account for AccountUpdater.
		'926' => ErrorCode::UNEXPECTED_VALUE, // Invalid iDEAL issuer provided.
		'927' => ErrorCode::MISSING_REQUIRED_DATA, // Missing payment details.
		'928' => ErrorCode::UNEXPECTED_VALUE, // Invalid forex type specified.
		'929' => ErrorCode::UNEXPECTED_VALUE, // Invalid base currency specified.
		'930' => ErrorCode::UNEXPECTED_VALUE, // Invalid target currency specified.
		'931' => ErrorCode::UNKNOWN, // There is no merchant with the code {0}.
		'950' => ErrorCode::UNEXPECTED_VALUE, // Invalid AcquirerAccount
		'951' => ErrorCode::UNKNOWN, // Configuration Error (acquirerIdentification)
		'952' => ErrorCode::UNKNOWN, // Configuration Error (acquirerPassword)
		'953' => ErrorCode::UNKNOWN, // Configuration Error (apiKey)
		'954' => ErrorCode::UNKNOWN, // Configuration Error (redirectUrl)
		'955' => ErrorCode::UNKNOWN, // Configuration Error (AcquirerAccountData)
		'956' => ErrorCode::UNKNOWN, // Configuration Error (currencyCode)
		'957' => ErrorCode::UNKNOWN, // Configuration Error (terminalId)
		'958' => ErrorCode::UNKNOWN, // Configuration Error (serialNumber)
		'959' => ErrorCode::UNKNOWN, // Configuration Error (password)
		'960' => ErrorCode::UNKNOWN, // Configuration Error (projectId)
		'961' => ErrorCode::UNKNOWN, // Configuration Error (merchantCategoryCode)
		'962' => ErrorCode::UNKNOWN, // Configuration Error (merchantName)
		'963' => ErrorCode::UNEXPECTED_VALUE, // Invalid company registration number
		'964' => ErrorCode::UNEXPECTED_VALUE, // Invalid company name
		'965' => ErrorCode::MISSING_REQUIRED_DATA, // Missing company details
		'966' => ErrorCode::UNKNOWN, // Configuration Error (privateKeyAlias)
		'967' => ErrorCode::UNKNOWN, // Configuration Error (publicKeyAlias)
		'1000' => ErrorCode::UNKNOWN, // Card number cannot be specified for Incontrol virtual card requests
		'1001' => ErrorCode::UNKNOWN, // Recurring not allowed for Incontrol virtual card requests
		'1002' => ErrorCode::UNEXPECTED_VALUE, // Invalid Authorisation Type supplied
		'2000' => ErrorCode::UNEXPECTED_VALUE, // Bank account details do not meet instruction format
		'29_001' => ErrorCode::UNEXPECTED_VALUE, // Field '{0}' may not exceed {1} characters.
		'29_100' => ErrorCode::UNKNOWN, // The request is larger than the allowed maximum of {0} bytes
		// Checkout error codes
		'14_002' => ErrorCode::MISSING_REQUIRED_DATA, // paymentData has not been provided in the request
		'14_003' => ErrorCode::UNEXPECTED_VALUE, // Invalid paymentData
		'14_004' => ErrorCode::MISSING_REQUIRED_DATA, // Missing payment method details
		'14_005' => ErrorCode::UNEXPECTED_VALUE, // Invalid payment method details
		'14_006' => ErrorCode::MISSING_REQUIRED_DATA, // paymentMethod object has not been provided in the request
		'14_007' => ErrorCode::UNEXPECTED_VALUE, // Invalid payment method data
		'14_008' => ErrorCode::UNKNOWN, // Session has expired
		'14_010' => ErrorCode::UNKNOWN, // The request contains no sdkVersion although the channel is set to Web
		'14_011' => ErrorCode::UNKNOWN, // The provided channel is conflicting the provided parameters
		'14_012' => ErrorCode::UNKNOWN, // The provided SDK token could not be parsed
		'14_013' => ErrorCode::UNKNOWN, // For HTML Response; origin has to be provided
		'14_014' => ErrorCode::UNKNOWN, // This end point requires the version to be specified
		'14_015' => ErrorCode::MISSING_REQUIRED_DATA, // Token is missing
		'14_016' => ErrorCode::UNEXPECTED_VALUE, // Invalid sdkVersion provided
		'14_017' => ErrorCode::UNKNOWN, // The provided SDK Token has an invalid timestamp
		'14_018' => ErrorCode::UNEXPECTED_VALUE, // Invalid payload provided
		'14_019' => ErrorCode::UNKNOWN, // The request does not contain sdkVersion or token
		'14_020' => ErrorCode::MISSING_REQUIRED_DATA, // No issuer selected
		'14_021' => ErrorCode::UNKNOWN, // Unknown terminal
		'14_022' => ErrorCode::MISSING_REQUIRED_DATA, // Missing uniqueTerminalId
		'14_023' => ErrorCode::UNKNOWN, // Installments were not configured in setup request
		'14_024' => ErrorCode::UNEXPECTED_VALUE, // Invalid open invoice request
		'14_026' => ErrorCode::UNKNOWN, // The selected flow is invalid
		'14_027' => ErrorCode::UNKNOWN, // The provided returnUrlQueryString is invalid
		'14_028' => ErrorCode::UNKNOWN, // 3D Auth Data is incomplete
		'14_029' => ErrorCode::UNKNOWN, // CVC is required for this payment but not provided
		'14_030' => ErrorCode::MISSING_REQUIRED_DATA, // Return URL is missing
		'14_031' => ErrorCode::MISSING_REQUIRED_DATA, // Bank account is missing
		'14_032' => ErrorCode::MISSING_REQUIRED_DATA, // Provide either threeds2.fingerprint or threeds2.challengeResult
		'14_033' => ErrorCode::UNEXPECTED_VALUE, // The provided fingerprint is invalid
		'14_034' => ErrorCode::UNEXPECTED_VALUE, // The provided fingerprint is invalid. Field affected: FIELD_NAME
		'14_035' => ErrorCode::MISSING_REQUIRED_DATA, // 'origin' or 'notificationURL' must be provided
		'14_036' => ErrorCode::MISSING_REQUIRED_DATA, // Missing required field 'channel'
		'14_037' => ErrorCode::UNEXPECTED_VALUE, // Provided orderData is invalid
		'14_0378' => ErrorCode::UNEXPECTED_VALUE, // Provided recurringExpiry format is invalid. Required format: yyyy-MM-dd'T'HH:mm:ssX, for example 2019-12-31T23:59:59+02:00
		'14_0379' => ErrorCode::UNEXPECTED_VALUE, // expiresAt is greater than the allowed date, max allowed: VALUE
		'14_0380' => ErrorCode::UNKNOWN, // PaymentData and MD belong to different requests, please submit the correct MD
		'14_0381' => ErrorCode::MISSING_REQUIRED_DATA, // Missing or invalid clientKey/originKey
		'14_0382' => ErrorCode::MISSING_REQUIRED_DATA, // Missing or invalid conversionId
		'14_0383' => ErrorCode::MISSING_REQUIRED_DATA, // Missing or invalid shopperStage
		'14_0384' => ErrorCode::MISSING_REQUIRED_DATA, // Missing or invalid details for provided shopperStage
		'14_0385' => ErrorCode::UNKNOWN, // PaymentLink is expired
		'14_0392' => ErrorCode::MISSING_REQUIRED_DATA, // Payment details are incomplete. Please provide both PaRes and MD.
		'14_0425' => ErrorCode::UNKNOWN, // Terminal with uniqueTerminalId '{0}' was not found.
		'14_0426' => ErrorCode::UNKNOWN, // Terminal with uniqueTerminalId '{0}' is not deployed.
		'14_0427' => ErrorCode::ACCOUNT_MISCONFIGURATION, // Insufficient permissions for terminal with uniqueTerminalId '{0}'.
		'14_0428' => ErrorCode::ACCOUNT_MISCONFIGURATION, // Merchant account is missing Pay by Link permission(s).
		'14_0430' => ErrorCode::UNKNOWN, // Provided merchant unknown.
		'14_0431' => ErrorCode::UNKNOWN, // Unknown terminal.
		'14_0432' => ErrorCode::UNKNOWN, // Unknown terminal.
		// 3DS2 error codes
		'15_001' => ErrorCode::MISSING_REQUIRED_DATA, // deviceChannel shall be present
		'15_002' => ErrorCode::MISSING_REQUIRED_DATA, // Required field FIELD_NAME missing for device channel DEVICE_CHANNEL_NAME
		'15_003' => ErrorCode::UNEXPECTED_VALUE, // Field FIELD_NAME should not be present for device channel DEVICE_CHANNEL_NAME
		'15_004' => ErrorCode::UNEXPECTED_VALUE, // Invalid value in field FIELD_NAME
		'15_005' => ErrorCode::UNEXPECTED_VALUE, // Unexpected field FIELD_NAME
		'15_006' => ErrorCode::MISSING_REQUIRED_DATA, // messageCategory shall be present
		'15_007' => ErrorCode::UNEXPECTED_VALUE, // Value of FIELD_NAME is too long (VALUE > MAX_ALLOWED_VALUE)
		'15_008' => ErrorCode::UNEXPECTED_VALUE, // Invalid merchant configuration for 3DS 2.0
		'15_009' => ErrorCode::MISSING_REQUIRED_DATA, // threeDS2Token is required
		'15_010' => ErrorCode::UNEXPECTED_VALUE, // Invalid deviceChannel value. Must be 'app', 'browser' or 'TRI'.
		'15_011' => ErrorCode::UNEXPECTED_VALUE, // pspReference is not a valid pspReference
		'15_012' => ErrorCode::MISSING_REQUIRED_DATA, // Result not present
		'15_013' => ErrorCode::UNKNOWN, // Cannot add 3DS2 authentication values because addRawThreeDSecureDetailsResult is not enabled
		'15_014' => ErrorCode::UNEXPECTED_VALUE, // Invalid threeDS2Token
		'15_015' => ErrorCode::UNEXPECTED_VALUE, // Invalid browserInfo provided
		'15_016' => ErrorCode::UNEXPECTED_VALUE, // Language must be a valid tag according to IETF BCP47 definition
		'15_017' => ErrorCode::UNKNOWN, // Cannot perform an authorisation on a 3DS2 transaction more than 12 hours after the transaction began
		'15_018' => ErrorCode::UNEXPECTED_VALUE, // colorDepth must be 1, 4, 8, 15, 16, 24, 32 or 48 bit
		'15_019' => ErrorCode::UNEXPECTED_VALUE, // stateOrProvince invalid
		'15_020' => ErrorCode::UNKNOWN, // invalid transStatus
		'15_021' => ErrorCode::UNEXPECTED_VALUE, // notificationURL cannot be localhost
		'15_022' => ErrorCode::UNEXPECTED_VALUE, // invalid authenticationOnly field
		'15_023' => ErrorCode::UNKNOWN, // threeDS2RequestData is not supported for threeDS2InMDFlow
		'15_024' => ErrorCode::UNKNOWN, // Result not present - Authentication older than 60 days
		// Amazon Pay error codes
		'5_601' => ErrorCode::UNKNOWN, // Unknown error for Amazon Pay
		'5_602' => ErrorCode::UNKNOWN, // Amazon Pay token doesn't exist for Amazon Pay
		'5_603' => ErrorCode::BAD_SIGNATURE, // Amazon Pay signature corrupt
		'5_604' => ErrorCode::UNEXPECTED_VALUE, // Invalid Amazon Pay value supplied: VALUE
		'5_605' => ErrorCode::UNKNOWN, // Could not find Merchant Private Key
		'5_606' => ErrorCode::UNKNOWN, // Amazon Pay token is not chargeable
		'5_607' => ErrorCode::UNEXPECTED_VALUE, // Invalid account
		'5_608' => ErrorCode::UNEXPECTED_VALUE, // Invalid refund status
		'5_609' => ErrorCode::MISSING_REQUIRED_DATA, // Missing header fields
		'5_610' => ErrorCode::UNKNOWN, // Amazon Pay is not available
		'5_611' => ErrorCode::UNKNOWN, // Rejected by Amazon
		'5_612' => ErrorCode::UNKNOWN, // Amazon Pay internal error
		'5_613' => ErrorCode::SERVER_TIMEOUT, // Amazon Pay timeout
		'5_614' => ErrorCode::UNEXPECTED_VALUE, // Amazon Pay sent invalid response
		'5_615' => ErrorCode::UNKNOWN, // Authentication Notification to Amazon Pay failed
		// Apple Pay error codes
		'5_001' => ErrorCode::UNKNOWN, // Apple Pay token amount-mismatch
		'5_002' => ErrorCode::UNEXPECTED_VALUE, // Invalid Apple Pay token
		'5_003' => ErrorCode::UNKNOWN, // Incorrect Apple Pay token version
		'5_004' => ErrorCode::UNKNOWN, // Could not find Merchant Private Key
		'5_005' => ErrorCode::UNKNOWN, // Could not find Merchant Public Key
		'5_006' => ErrorCode::BAD_SIGNATURE, // Apple Pay signature corrupt
		'5_007' => ErrorCode::MISSING_REQUIRED_DATA, // Missing certificate for Apple Pay signature
		'5_008' => ErrorCode::UNKNOWN, // Our Test system does not accept Live tokens
		// Google Pay error codes
		'5_202' => ErrorCode::UNEXPECTED_VALUE, // Invalid PayWithGoogle token
		'5_206' => ErrorCode::BAD_SIGNATURE, // PayWithGoogle signature corrupt
		'5_208' => ErrorCode::UNKNOWN, // PayWithGoogle token already expired
	];

	/**
	 * @throws ApiException
	 */
	public static function throwOnAdyenError( $adyenResponse ): void {
		$exceptionCode = null;
		$exceptionMessage = null;

		if ( $adyenResponse === null ) {
			$exceptionCode = ErrorCode::NO_RESPONSE;
			$exceptionMessage = 'No response or malformed JSON';
		} elseif (
			isset( $adyenResponse['errorCode'] ) &&
			isset( self::$fatalErrorCodes[$adyenResponse['errorCode']] )
		) {
			$exceptionCode = self::$fatalErrorCodes[$adyenResponse['errorCode']];
			$exceptionMessage = $adyenResponse['message'] ?? 'Error in Adyen response';
		} elseif (
			isset( $adyenResponse['errorCode'] ) &&
			!isset( self::$fatalErrorCodes[$adyenResponse['errorCode']] ) &&
			ValidationErrorMapper::getValidationErrorField( $adyenResponse['errorCode'] ) === null
		) {
			$exceptionCode = ErrorCode::UNKNOWN;
			$exceptionMessage = 'Unknown Adyen error code ' . $adyenResponse['errorCode'];
		}
		if ( $exceptionCode !== null ) {
			$exception = new ApiException( $exceptionMessage, $exceptionCode );
			$exception->setRawErrors( [ $adyenResponse ] );
			throw $exception;
		}
	}
}
