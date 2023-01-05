<?php namespace SmashPig\PaymentProviders\Adyen\WSDL;

class NotificationRequest {
	/** @var boolean $live True if the notification was received from the live (not test) platform */
	public $live;

	/** @var array of NotificationRequestItem */
	public $notificationItems;
}

class NotificationRequestItem {
	/** @var  */
	public $additionalData; // anyType2anyTypeMap
	public $amount; // Amount
	public $eventCode; // string
	public $eventDate; // dateTime
	public $merchantAccountCode; // string
	public $merchantReference; // string
	public $operations; // ArrayOfString
	public $originalReference; // string
	public $paymentMethod; // string
	public $pspReference; // string
	public $reason; // string
	public $success; // boolean
}

class anyType2anyTypeMap {
	public $entry; // entry
}

class entry {
	public $key; // anyType
	public $value; // anyType
}

class sendNotification {
	public $notification; // NotificationRequest
}

class sendNotificationResponse {
	public $notificationResponse; // string
}

class Amount {
	public $currency; // string
	public $value; // long
}

class ServiceException {
	public $error; // Error
	public $type; // Type
}

class Error {
	const Unknown = 'Unknown';
	const NotAllowed = 'NotAllowed';
	const NoAmountSpecified = 'NoAmountSpecified';
	const InvalidCardNumber = 'InvalidCardNumber';
	const UnableToDetermineVariant = 'UnableToDetermineVariant';
	const CVCisNotTheRightLength = 'CVCisNotTheRightLength';
	const InvalidLoyaltyAmountSpecified = 'InvalidLoyaltyAmountSpecified';
	const InvalidPaRes3dSecure = 'InvalidPaRes3dSecure';
	const SessionAlreadyUsed = 'SessionAlreadyUsed';
	const RecurringNotEnabled = 'RecurringNotEnabled';
	const InvalidBankAccountNumber = 'InvalidBankAccountNumber';
	const InvalidVariant = 'InvalidVariant';
	const InvalidBankDetailsMissing = 'InvalidBankDetailsMissing';
	const InvalidBankCountry = 'InvalidBankCountry';
	const BankCountryNotSupported = 'BankCountryNotSupported';
	const OpenInvoiceLinesMissing = 'OpenInvoiceLinesMissing';
	const OpenInvoiceLineInvalid = 'OpenInvoiceLineInvalid';
	const OpenInvoiceLinesInvalidTotalAmount = 'OpenInvoiceLinesInvalidTotalAmount';
	const InvalidDateOfBirth = 'InvalidDateOfBirth';
	const InvalidBillingAddress = 'InvalidBillingAddress';
	const InvalidDeliveryAddress = 'InvalidDeliveryAddress';
	const InvalidShopperName = 'InvalidShopperName';
	const MissingShopperEmail = 'MissingShopperEmail';
	const MissingShopperReference = 'MissingShopperReference';
	const MissingPhoneNumber = 'MissingPhoneNumber';
	const MobilePhoneNumberOnly = 'MobilePhoneNumberOnly';
	const InvalidPhoneNumber = 'InvalidPhoneNumber';
	const RecurringInvalidContract = 'RecurringInvalidContract';
	const BankAccountOrBankLocationIdNotValid = 'BankAccountOrBankLocationIdNotValid';
	const AccountHolderMissing = 'AccountHolderMissing';
	const CardHolderNameMissing = 'CardHolderNameMissing';
	const InvalidExpiry = 'InvalidExpiry';
	const MissingMerchantReference = 'MissingMerchantReference';
	const BillingAddressCityProblem = 'BillingAddressCityProblem';
	const BillingAddressStreetProblem = 'BillingAddressStreetProblem';
	const BillingAddressHouseNumberOrNameProblem = 'BillingAddressHouseNumberOrNameProblem';
	const BillingAddressCountryProblem = 'BillingAddressCountryProblem';
	const BillingAddressStateOrProvinceProblem = 'BillingAddressStateOrProvinceProblem';
	const OpenInvoiceFailedToRetrieveDetails = 'OpenInvoiceFailedToRetrieveDetails';
	const InvalidAmount = 'InvalidAmount';
	const UnsupportedCurrency = 'UnsupportedCurrency';
	const RecurringRequiredFields = 'RecurringRequiredFields';
	const InvalidCardExpiryOnInPast = 'InvalidCardExpiryOnInPast';
	const InvalidCardExpiry = 'InvalidCardExpiry';
	const BankNameOrBankLocationIsNotValid = 'BankNameOrBankLocationIsNotValid';
	const InvalidIdealMerchantReturnUrl = 'InvalidIdealMerchantReturnUrl';
	const InvalidCardStartDateInFuture = 'InvalidCardStartDateInFuture';
	const InvalidIssuerCountryCode = 'InvalidIssuerCountryCode';
	const InvalidSocialSecurityNumber = 'InvalidSocialSecurityNumber';
	const DeliveryAddressCityProblem = 'DeliveryAddressCityProblem';
	const DeliveryAddressStreetProblem = 'DeliveryAddressStreetProblem';
	const DeliveryAddressHouseNumberOrNameProblem = 'DeliveryAddressHouseNumberOrNameProblem';
	const DeliveryAddressCountryProblem = 'DeliveryAddressCountryProblem';
	const DeliveryAddressStateOrProvinceProblem = 'DeliveryAddressStateOrProvinceProblem';
	const InvalidInstallments = 'InvalidInstallments';
	const InvalidCVC = 'InvalidCVC';
	const MissingAdditionalData = 'MissingAdditionalData';
	const MissingAcquirer = 'MissingAcquirer';
	const MissingAuthorisationMid = 'MissingAuthorisationMid';
	const MissingFields = 'MissingFields';
	const MissingRequiredField = 'MissingRequiredField';
	const InvalidNumberOfRequests = 'InvalidNumberOfRequests';
	const PayoutStoreDetailNotAllowed = 'PayoutStoreDetailNotAllowed';
	const InvalidIBAN = 'InvalidIBAN';
	const InconsistentIban = 'InconsistentIban';
	const InvalidBIC = 'InvalidBIC';
	const Invoice_MissingInvoiceProject = 'Invoice_MissingInvoiceProject';
	const Invoice_MissingInvoiceBatch = 'Invoice_MissingInvoiceBatch';
	const Invoice_MissingCreditorAccount = 'Invoice_MissingCreditorAccount';
	const Invoice_MissingProjectCode = 'Invoice_MissingProjectCode';
	const Invoice_CreditorAccountNotFound = 'Invoice_CreditorAccountNotFound';
	const Invoice_ProjectNotFound = 'Invoice_ProjectNotFound';
	const Invoice_InvoiceProjectCouldNotBeCreated = 'Invoice_InvoiceProjectCouldNotBeCreated';
	const Invoice_InvoiceBatchAlreadyExists = 'Invoice_InvoiceBatchAlreadyExists';
	const Invoice_InvoiceBatchCouldNotBeCreated = 'Invoice_InvoiceBatchCouldNotBeCreated';
	const Invoice_InvoiceBatchPeriodExceeded = 'Invoice_InvoiceBatchPeriodExceeded';
	const InvoiceMissingInvoice = 'InvoiceMissingInvoice';
	const InvoiceMissingCreditorAccountCode = 'InvoiceMissingCreditorAccountCode';
	const InvoiceMissingDebtorCode = 'InvoiceMissingDebtorCode';
	const InvoiceMissingDebtorName = 'InvoiceMissingDebtorName';
	const InvoiceMissingDebtorEmailAddress = 'InvoiceMissingDebtorEmailAddress';
	const InvoiceMissingDebtorCountryCode = 'InvoiceMissingDebtorCountryCode';
	const InvoiceMissingInvoicePayment = 'InvoiceMissingInvoicePayment';
	const InvoiceMissingReference = 'InvoiceMissingReference';
	const InvoiceInvalidCreditorAccount = 'InvoiceInvalidCreditorAccount';
	const InvoiceInvalidDebtor = 'InvoiceInvalidDebtor';
	const InvoiceInvalidPaymentAmount = 'InvoiceInvalidPaymentAmount';
	const InvoiceInvalidPaymentCurrency = 'InvoiceInvalidPaymentCurrency';
	const InvoiceInvalidDebtorType = 'InvoiceInvalidDebtorType';
	const InvoiceDoesNotExists = 'InvoiceDoesNotExists';
	const InvoiceDoesNotExistsForDebtor = 'InvoiceDoesNotExistsForDebtor';
	const InvoicePaymentAmountTooHigh = 'InvoicePaymentAmountTooHigh';
	const InvoiceAlreadyPaid = 'InvoiceAlreadyPaid';
	const InvoiceErrorStoreDebtor = 'InvoiceErrorStoreDebtor';
	const InvoiceErrorStoreInvoice = 'InvoiceErrorStoreInvoice';
	const InvoiceErrorCheckInvoiceReference = 'InvoiceErrorCheckInvoiceReference';
	const InvoiceErrorSearchInvoices = 'InvoiceErrorSearchInvoices';
	const InvoiceErrorNoInvoiceConfiguration = 'InvoiceErrorNoInvoiceConfiguration';
	const InvoiceErrorInvalidInvoiceConfiguration = 'InvoiceErrorInvalidInvoiceConfiguration';
	const RechargeContractNotFound = 'RechargeContractNotFound';
	const RechargeTooManyPaymentDetails = 'RechargeTooManyPaymentDetails';
	const RechargeInvalidContract = 'RechargeInvalidContract';
	const RechargeDetailNotFound = 'RechargeDetailNotFound';
	const RechargeFailedToDisable = 'RechargeFailedToDisable';
	const RechargeDetailNotAvailableForContract = 'RechargeDetailNotAvailableForContract';
	const RechargeNoApplicableContractTypeLeft = 'RechargeNoApplicableContractTypeLeft';
	const InvalidMerchantAccount = 'InvalidMerchantAccount';
	const RequestMissing = 'RequestMissing';
	const InternalError = 'InternalError';
	const UnableToProcess = 'UnableToProcess';
	const PaymentDetailsAreNotSupported = 'PaymentDetailsAreNotSupported';
	const OriginalPspReferenceInvalidForThisEnvironment = 'OriginalPspReferenceInvalidForThisEnvironment';
	const InvalidAcquirerAccount = 'InvalidAcquirerAccount';
	const InvalidConfigurationAuthorisationMid = 'InvalidConfigurationAuthorisationMid';
	const InvalidConfigurationAcquirerPassword = 'InvalidConfigurationAcquirerPassword';
	const InvalidConfigurationApiKey = 'InvalidConfigurationApiKey';
	const InvalidConfigurationRedirectUrl = 'InvalidConfigurationRedirectUrl';
	const InvalidConfigurationAcquirerAccountData = 'InvalidConfigurationAcquirerAccountData';
	const InvalidConfigurationCurrencyCode = 'InvalidConfigurationCurrencyCode';
	const InvalidConfigurationAuthorisationTerminalId = 'InvalidConfigurationAuthorisationTerminalId';
	const InvalidConfigurationSerialNumber = 'InvalidConfigurationSerialNumber';
	const InvalidConfigurationPassword = 'InvalidConfigurationPassword';
	const InvalidConfigurationProjectId = 'InvalidConfigurationProjectId';
	const InvalidConfigurationMerchantCategoryCode = 'InvalidConfigurationMerchantCategoryCode';
	const InvalidConfigurationMerchantName = 'InvalidConfigurationMerchantName';
}

class Type {
	const internal = 'internal';
	const validation = 'validation';
	const security = 'security';
	const configuration = 'configuration';
}

class ClassMap {
	public $classmap = array(
		'NotificationRequest' => 'NotificationRequest',
		'NotificationRequestItem' => 'NotificationRequestItem',
		'anyType2anyTypeMap' => 'anyType2anyTypeMap',
		'entry' => 'entry',
		'sendNotification' => 'sendNotification',
		'sendNotificationResponse' => 'sendNotificationResponse',
		'Amount' => 'Amount',
		'ServiceException' => 'ServiceException',
		'Error' => 'Error',
		'Type' => 'Type',
	);
}
