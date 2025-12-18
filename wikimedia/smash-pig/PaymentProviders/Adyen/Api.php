<?php namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Context;
use SmashPig\Core\Helpers\UniqueId;
use SmashPig\Core\Http\OutboundRequest;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\PaymentData\RecurringModel;
use UnexpectedValueException;

class Api {

	/**
	 * Constants set inline with Adyens docs
	 * https://docs.adyen.com/classic-integration/recurring-payments/authorise-a-recurring-payment#recurring-payments
	 * API
	 * https://docs.adyen.com/online-payments/tokenization/create-and-use-tokens?tab=subscriptions_2#make-subscription-payments
	 */
	const RECURRING_CONTRACT = 'RECURRING';
	const RECURRING_SHOPPER_INTERACTION = 'ContAuth';
	const RECURRING_SHOPPER_INTERACTION_SETUP = 'Ecommerce';

	/**
	 * These two happen to have the same string values as the cross-processor constants in RecurringModel, but
	 * we define Adyen-specific constants here in case Adyen ever decides to change their strings. Code that
	 * builds the request to Adyen API endpoints should use these constants instead of those in RecurringModel.
	 */
	const RECURRING_MODEL_SUBSCRIPTION = 'Subscription';
	const RECURRING_MODEL_CARD_ON_FILE = 'CardOnFile';

	/**
	 * @var string Name of the merchant account
	 */
	protected $account;

	/**
	 * @var string REST API key
	 */
	protected $apiKey;

	/**
	 * Default base path for REST API calls
	 *
	 * @var string
	 */
	protected $restBaseUrl;

	/**
	 * Base path for REST API calls to the recurring service
	 * (see https://docs.adyen.com/api-explorer/#/Recurring/v67/overview)
	 * @var string
	 */
	protected $recurringBaseUrl;

	/**
	 * Base path for REST API calls to the payment service
	 * (see https://docs.adyen.com/api-explorer/Payment/68/overview)
	 * @var string
	 */
	protected $paymentBaseUrl;

	/**
	 * Base path for REST API calls to the data protection service
	 * (see https://docs.adyen.com/development-resources/data-protection-api)
	 * @var string
	 */
	protected $dataProtectionBaseUrl;

	/**
	 * @var bool
	 */
	protected $enableAutoRescue;

	/**
	 * @var int
	 */
	protected $maxDaysToRescue;

	public function __construct() {
		$c = Context::get()->getProviderConfiguration();
		$this->account = array_keys( $c->val( 'accounts' ) )[0]; // this feels fragile
		$this->restBaseUrl = $c->val( 'rest-base-url' );
		$this->recurringBaseUrl = $c->val( 'recurring-base-url' );
		$this->paymentBaseUrl = $c->val( 'payment-base-url' );
		$this->dataProtectionBaseUrl = $c->val( 'data-protection-base-url' );
		$this->apiKey = $c->val( "accounts/{$this->account}/ws-api-key" );
		$this->enableAutoRescue = $c->val( 'enable-auto-rescue' );
		$this->maxDaysToRescue = $c->val( 'max-days-to-rescue' );
	}

	/**
	 * Uses the rest API to create a payment using a blob of encrypted
	 * payment data as returned by the Drop-In Web integration.
	 *
	 * @param array $params
	 * amount, currency, encrypted_payment_details (blob from front-end)
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPaymentFromEncryptedDetails( $params ) {
		// TODO: use txn template / mapping a la Ingenico?
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'paymentMethod' => $params['encrypted_payment_data'],
			'merchantAccount' => $this->account,
			'additionalData' => [
				'manualCapture' => true,
			],
		];
		// TODO: map this from $params['payment_method']
		// 'scheme' corresponds to our 'cc' value
		$restParams['paymentMethod']['type'] = 'scheme';
		if ( !empty( $params['return_url'] ) ) {
			$restParams['returnUrl'] = $params['return_url'];
			$parsed = parse_url( $params['return_url'] );
			$restParams['origin'] = $parsed['scheme'] . '://' . $parsed['host'];
			if ( !empty( $parsed['port'] ) ) {
				$restParams['origin'] .= ':' . $parsed['port'];
			}
			// If there is a return URL we are definitely coming via the 'Web' channel
			$restParams['channel'] = 'Web';
		}
		if ( !empty( $params['browser_info'] ) ) {
			$restParams['browserInfo'] = $params['browser_info'];
		}
		$restParams = array_merge( $restParams, $this->getContactInfo( $params ) );
		// This is specifically for credit cards
		if ( empty( $restParams['paymentMethod']['holderName'] ) ) {
			// TODO: FullName staging helper
			$nameParts = [];
			if ( !empty( $params['first_name'] ) ) {
				$nameParts[] = $params['first_name'];
			}
			if ( !empty( $params['last_name'] ) ) {
				$nameParts[] = $params['last_name'];
			}
			$fullName = implode( ' ', $nameParts );
			$restParams['paymentMethod']['holderName'] = $fullName;
		}
		$restParams['shopperStatement'] = $params['description'] ?? '';
		$isRecurring = $params['recurring'] ?? '';
		if ( $isRecurring ) {
			$restParams = array_merge( $restParams, $this->addRecurringParams( $params, true ) );
		}
		$result = $this->makeRestApiCall( $restParams, 'payments', 'POST', __FUNCTION__ );
		return $result['body'];
	}

	protected function getBillingAddress( array $params ): array {
		$billingInfo = [
			'billingAddress' => [
				'city' => $params['city'] ?? 'NA',
				'country' => $params['country'] ?? 'ZZ',
				'houseNumberOrName' => $params['supplemental_address_1'] ?? '', // optional, blank would be fine
				'postalCode' => $params['postal_code'] ?? 'NA',
				'stateOrProvince' => $params['state_province'] ?? 'NA',
				'street' => $params['street_address'] ?? 'NA'
			]
		];
		return $billingInfo;
	}

	/**
	 * Formats contact info for payment creation. Takes our normalized
	 * parameter names and maps them across to Adyen's parameter names,
	 * and adds default values suggested by Adyen tech support.
	 *
	 * @param array $params normalized params from calling code
	 * @return array contact ID parameters formatted Adyen-style
	 */
	protected function getContactInfo( array $params ): array {
		$contactInfo = [
			'shopperEmail' => $params['email'] ?? '',
			'shopperIP' => $params['user_ip'] ?? '',
		];
		if ( !empty( $params['full_name'] ) && ( empty( $params['first_name'] ) || empty( $params['last_name'] ) ) ) {
			$nameParts = explode( ' ', $params['full_name'], 2 );
			$params['first_name'] = $nameParts[0];
			if ( count( $nameParts ) > 1 ) {
				$params['last_name'] = $nameParts[1];
			}
		}
		$contactInfo['shopperName'] = [
			'firstName' => $params['first_name'] ?? '',
			'lastName' => $params['last_name'] ?? ''
		];
		$contactInfo = array_merge( $contactInfo, $this->getBillingAddress( $params ) );
		return $contactInfo;
	}

	/**
	 * Uses the rest API to create a payment from a saved token
	 *
	 * @param array $params
	 * amount, currency, payment_method, recurring_payment_token, processor_contact_id
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPaymentFromToken( array $params ) {
		$restParams = [
			'amount' => [
				'currency' => $params['currency'],
				'value' => AdyenCurrencyRoundingHelper::getAmountInMinorUnits(
					$params['amount'], $params['currency']
				)
			],
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account
		];

		$restParams['additionalData']['manualCapture'] = $params['manual_capture'];
		$restParams['paymentMethod']['type'] = $params['payment_method'];
		// storedPaymentMethodId - token adyen sends back on auth
		$restParams['paymentMethod']['storedPaymentMethodId'] = $params['recurring_payment_token'];
		$restParams['shopperReference'] = $params['processor_contact_id'];
		$restParams['shopperInteraction'] = static::RECURRING_SHOPPER_INTERACTION;
		$restParams['recurringProcessingModel'] = static::RECURRING_MODEL_SUBSCRIPTION;
		$restParams = array_merge( $restParams, $this->getContactInfo( $params ) );
		if ( $params['payment_method'] === 'ach' ) {
			// ach billing address optional,
			// if pass needs to pass country and state,
			// for recurring token charge we have no state info, so do not pass
			unset( $restParams['billingAddress'] );
		}
		// T351340 we will do credit card which have method scheme first and then add SEPA which use sepadirectdebit later
		if ( $this->enableAutoRescue && $params['payment_method'] !== 'sepadirectdebit' ) {
			$restParams['additionalData'] = [
				'autoRescue' => true,
				'maxDaysToRescue' => $this->maxDaysToRescue
			];
		}
		$result = $this->makeRestApiCall( $restParams, 'payments', 'POST', __FUNCTION__ );
		return $result['body'];
	}

	/**
	 * Uses the rest API to create a bank transfer payment from the
	 * Component web integration. Handles NL (iDEAL 2.0) and CZ bank transfer.
	 *
	 * @param array $params
	 * amount, currency, value, issuer_id (for CZ), return_url
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createBankTransferPaymentFromCheckout( $params ) {
		$typesByCountry = [
			'NL' => 'ideal',
			'CZ' => 'onlineBanking_CZ'
		];
		if ( empty( $params['country'] ) || !array_key_exists( $params['country'], $typesByCountry ) ) {
			throw new UnexpectedValueException(
				'Needs supported country: (one of ' . implode( ', ', array_keys( $typesByCountry ) ) . ')'
			);
		}
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account,
			'paymentMethod' => [
				'type' => $typesByCountry[$params['country']],
			],
			'returnUrl' => $params['return_url'],
			'additionalData' => [
				'manualCapture' => false,
			],
		];
		if ( isset( $params['issuer_id'] ) ) {
			$restParams['paymentMethod']['issuer'] = $params['issuer_id'];
		}
		$isRecurring = $params['recurring'] ?? '';
		if ( $isRecurring ) {
			$restParams = array_merge( $restParams, $this->addRecurringParams( $params, true ) );
		}
		$restParams = array_merge( $restParams, $this->getContactInfo( $params ) );

		$result = $this->makeRestApiCall( $restParams, 'payments', 'POST', __FUNCTION__ );
		return $result['body'];
	}

	/**
	 * Uses the rest API to create a SEPA direct deposit transfer
	 *
	 * @param array $params
	 * amount, currency, order_id, iban, full_name
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createSEPABankTransferPayment( $params ) {
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account,
			'paymentMethod' => [
				'type' => 'sepadirectdebit',
				'sepa.ownerName' => $params['full_name'], // the name on the SEPA bank account.
				'sepa.ibanNumber' => $params['iban'], // the IBAN of the bank account, (do not encrypt)
			]
		];
		$isRecurring = $params['recurring'] ?? '';
		if ( $isRecurring ) {
			$restParams = array_merge( $restParams, $this->addRecurringParams( $params, true ) );
		}
		// billing address optional
		$restParams = array_merge( $restParams, $this->getContactInfo( $params ) );

		$result = $this->makeRestApiCall(
			$restParams,
			'payments',
			'POST',
			__FUNCTION__
		);

		return $result['body'];
	}

	public function createACHDirectDebitPayment( $params ) {
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account,
			'paymentMethod' => [
				'type' => 'ach',
				'encryptedBankAccountNumber' => $params['encrypted_bank_account_number'], // encrypted account number
				'bankAccountType' => $params['bank_account_type'], // checking or savings
				'encryptedBankLocationId' => $params['encrypted_bank_location_id'], // encrypted ACH routing number of the account
				'ownerName' => $params['full_name'] // the name on the bank account
			]
		];
		$isRecurring = $params['recurring'] ?? '';
		if ( $isRecurring ) {
			$restParams = array_merge( $restParams, $this->addRecurringParams( $params, true ) );
		}
		$restParams = array_merge( $restParams, $this->getContactInfo( $params ) );
		if ( $restParams['billingAddress']['stateOrProvince'] === 'NA' ) {
			// ach billing address optional,
			// if pass needs to pass country and state, for T360825 no need to pass
			unset( $restParams['billingAddress'] );
		}
		$result = $this->makeRestApiCall(
			$restParams,
			'payments',
			'POST',
			__FUNCTION__
		);

		return $result['body'];
	}

	public function createGooglePayPayment( $params ) {
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account,
			'paymentMethod' => [
				'type' => 'googlepay',
				'googlePayToken' => $params['payment_token']
			],
			'additionalData' => [
				'manualCapture' => true,
			],
		];
		$isRecurring = $params['recurring'] ?? '';
		if ( $isRecurring ) {
			$restParams = array_merge( $restParams, $this->addRecurringParams( $params, true ) );
		}
		$restParams = array_merge( $restParams, $this->getContactInfo( $params ) );

		$result = $this->makeRestApiCall(
			$restParams,
			'payments',
			'POST',
			__FUNCTION__
		);

		return $result['body'];
	}

	public function createApplePayPayment( $params ) {
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'reference' => $params['order_id'],
			'merchantAccount' => $this->account,
			'paymentMethod' => [
				'type' => 'applepay',
				'applePayToken' => $params['payment_token']
			],
			'additionalData' => [
				'manualCapture' => true,
			],
		];
		$isRecurring = $params['recurring'] ?? '';
		if ( $isRecurring ) {
			$restParams = array_merge( $restParams, $this->addRecurringParams( $params, true ) );
		}
		$restParams = array_merge( $restParams, $this->getContactInfo( $params ) );

		$result = $this->makeRestApiCall(
			$restParams,
			'payments',
			'POST',
			__FUNCTION__
		);

		return $result['body'];
	}

	/**
	 * Get an Apple Pay session directly from Apple without going through
	 * Adyen's servers. Note: only needed when using your own merchant
	 * certificate. When using Adyen's merchant certificate, this is all
	 * handled for you in Adyen's code.
	 * https://developer.apple.com/documentation/apple_pay_on_the_web/apple_pay_js_api/requesting_an_apple_pay_payment_session
	 */
	public function createApplePaySession( array $params ): array {
		$request = new OutboundRequest( $params['validation_url'], 'POST' );
		$request->setBody( json_encode( [
			// Your Apple Pay merchant ID
			'merchantIdentifier' => $params['merchant_identifier'],
			// A string of 64 or fewer UTF-8 characters containing the canonical name
			// for your store, suitable for display. Do not localize the name.
			'displayName' => $params['display_name'],
			// For Apple Pay JS this should always be 'web'
			'initiative' => 'web',
			// fully qualified domain name associated with your Apple Pay Merchant Identity Certificate
			'initiativeContext' => $params['domain_name']
		] ) )
			->setCertPath( $params['certificate_path'] )
			->setCertPassword( $params['certificate_password'] )
			->setLogTag( __FUNCTION__ );
		$response = $request->execute();
		return json_decode( $response['body'], true );
	}

	/**
	 * Refund a payment
	 *
	 * @param array $params
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function refundPayment( array $params ) {
		$restParams = [
			'amount' => $this->getArrayAmount( $params ),
			'merchantAccount' => $this->account,
		];
		$path = "payments/{$params['gateway_txn_id']}/refunds";

		$result = $this->makeRestApiCall( $restParams, $path, 'POST', __FUNCTION__ );
		return $result['body'];
	}

	/**
	 * Gets more details when no final state has been reached
	 * on the /payments call. Redirect payments will need this.
	 *
	 * @param string $redirectResult
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentDetails( $redirectResult ) {
		$restParams = [
			'details' => [
				'redirectResult' => $redirectResult
			]
		];
		$result = $this->makeRestApiCall( $restParams, 'payments/details', 'POST', __FUNCTION__ );
		return $result['body'];
	}

	/**
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentMethods( $params ) {
		$restParams = [
			'merchantAccount' => $this->account,
			'channel' => $params['channel'] ?? 'Web',
		];

		if ( !empty( $params['amount'] ) && !empty( $params['currency'] ) ) {
			$restParams['amount'] = $this->getArrayAmount( $params );
		}
		if ( !empty( $params['country'] ) ) {
			$restParams['countryCode'] = $params['country'];
		}
		if ( !empty( $params['language'] ) ) {
			// shopperLocale format needs to be language-country nl-NL en-NL
			$restParams['shopperLocale'] = str_replace( '_', '-', $params['language'] );
		}
		if ( !empty( $params['processor_contact_id'] ) ) {
			// We send processor_contact_id as the shopper reference from the front-end
			$restParams['shopperReference'] = $params['processor_contact_id'];
		}

		$result = $this->makeRestApiCall( $restParams, 'paymentMethods', 'POST', __FUNCTION__ );
		return $result['body'];
	}

	/**
	 * Uses the rest API to return saved payment details. These will include a token
	 * that can be used to make recurring donations or further charges without the
	 * shopper needing to input their card details again.
	 *
	 * @param string $shopperReference An identifying string we assign to the shopper
	 *  when we first store (tokenize) the payment details.
	 * @return array A list of saved payment methods with tokens and other details.
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getSavedPaymentDetails( string $shopperReference ): array {
		$restParams = [
			'merchantAccount' => $this->account,
			'shopperReference' => $shopperReference,
			'recurring' => [
				'contract' => self::RECURRING_CONTRACT,
			],
		];

		$result = $this->makeRestApiCall(
			$restParams, 'listRecurringDetails', 'POST', __FUNCTION__, $this->recurringBaseUrl
		);
		return $result['body'];
	}

	/**
	 * https://docs.adyen.com/development-resources/data-protection-api#submit-a-subject-erasure-request
	 *
	 * @param string $gatewayTransactionId For Adyen this is called the PSP Reference
	 * @return array usually just [ 'result' => 'SUCCESS' ]
	 * @throws \SmashPig\Core\ApiException
	 */
	public function deleteDataForPayment( string $gatewayTransactionId ): array {
		$restParams = [
			'merchantAccount' => $this->account,
			'pspReference' => $gatewayTransactionId,
			'forceErasure' => true
		];
		$result = $this->makeRestApiCall(
			$restParams, 'requestSubjectErasure', 'POST', __FUNCTION__, $this->dataProtectionBaseUrl
		);
		return $result['body'];
	}

	/**
	 * @param array $params array of parameters to be JSON encoded and sent to the API
	 * @param string $path REST path (entity name, possible id, possible action)
	 * @param string $method HTTP method, usually GET or POST
	 * @param string|null $alternateBaseUrl By default, the base URL used will be the restBaseUrl.
	 *  To use an alternate base URL, pass it here
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	protected function makeRestApiCall(
		array $params, string $path, string $method, string $logTag, ?string $alternateBaseUrl = null
	): array {
		$basePath = $alternateBaseUrl ?? $this->restBaseUrl;
		$url = $basePath . '/' . $path;
		$request = new OutboundRequest( $url, $method );
		$request->setBody( json_encode( $params ) )
			->setHeader( 'x-API-key', $this->apiKey )
			->setHeader( 'content-type', 'application/json' )
			->setLogTag( $logTag );
		if ( $method === 'POST' ) {
			// Set the idempotency header in case we retry on timeout
			// https://docs.adyen.com/development-resources/api-idempotency
			$request->setHeader( 'Idempotency-Key', UniqueId::generate() );
		}
		$response = $request->execute();
		$response['body'] = json_decode( $response['body'], true );
		ExceptionMapper::throwOnAdyenError( $response['body'] );
		return $response;
	}

	/**
	 * Approve a payment that has been authorized. In credit-card terms, this
	 * captures the payment.
	 *
	 * @param array $params Needs keys 'gateway_txn_id', 'currency', and 'amount' set
	 * @return bool|array
	 */
	public function approvePayment( array $params ) {
		$restParams = [
			'amount' => [
				'currency' => $params['currency'],
				'value' => AdyenCurrencyRoundingHelper::getAmountInMinorUnits(
					$params['amount'], $params['currency']
				)
			],
			'merchantAccount' => $this->account
		];
		$path = "payments/{$params['gateway_txn_id']}/captures";

		$tl = new TaggedLogger( 'RawData' );
		$tl->info( "Launching REST capture request for {$params['gateway_txn_id']}", $restParams );

		try {
			$result = $this->makeRestApiCall( $restParams, $path, 'POST', __FUNCTION__ );
		} catch ( \Exception $ex ) {
			// FIXME shouldn't we let the ApiException bubble up?
			Logger::error( 'REST capture request threw exception!', $params, $ex );
			return false;
		}
		return $result['body'];
	}

	/**
	 * Cancels a payment that may already be authorized
	 *
	 * @param string $pspReference The Adyen-side identifier, aka gateway_txn_id
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancel( string $pspReference ): array {
		$restParams = [
			'merchantAccount' => $this->account
		];
		// TODO: Adyen supports a merchant reference for the cancellation
		// but we'll need to change our ICancelablePaymentProvider to
		// support an array of parameters.
		$path = "payments/$pspReference/cancels";
		$result = $this->makeRestApiCall( $restParams, $path, 'POST', __FUNCTION__ );
		return $result['body'];
	}

	/**
	 * cancel auto rescue with the rescue reference
	 *
	 * @param string $rescueReference
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelAutoRescue( string $rescueReference ) {
		$restParams = [
			'merchantAccount' => $this->account,
			'originalReference' => $rescueReference,
			'additionalData' => [
				'cancellationType' => 'autoRescue',
			]
		];

		$result = $this->makeRestApiCall(
			$restParams, 'cancel', 'POST', __FUNCTION__, $this->paymentBaseUrl
		);
		return $result['body'];
	}

	/**
	 * Convenience function for formatting amounts in REST calls
	 *
	 * @param array $params
	 * @return array
	 */
	private function getArrayAmount( array $params ): array {
		return [
			'currency' => $params['currency'],
			'value' => AdyenCurrencyRoundingHelper::getAmountInMinorUnits(
				$params['amount'], $params['currency']
			)
		];
	}

	/**
	 * Adds the parameters to set up a recurring payment.
	 *
	 * @param array $params
	 * @param bool $needInteractionAndModel Set to 'true' for card or Apple Pay transactions
	 *  which need the shopperInteraction and recurringProcessModel parameters set.
	 * @return array
	 */
	private function addRecurringParams( $params, bool $needInteractionAndModel ) {
		// credit card, apple pay, and iDeal all need shopperReference and storePaymentMethod
		$recurringParams['shopperReference'] = $params['order_id'];
		$recurringParams['storePaymentMethod'] = true;

		if ( $needInteractionAndModel ) {
			// credit card and apple pay also need shopperInteraction and recurringProcessingModel
			$recurringParams['shopperInteraction'] = static::RECURRING_SHOPPER_INTERACTION_SETUP;

			// By default make recurring charges as 'Subscription' but allow for Card On File
			// in case of speculative tokenization (e.g. for monthly convert).
			$recurringModel = $params['recurring_model'] ?? RecurringModel::SUBSCRIPTION;
			switch ( $recurringModel ) {
				case RecurringModel::SUBSCRIPTION:
					$recurringParams['recurringProcessingModel'] = static::RECURRING_MODEL_SUBSCRIPTION;
					break;
				case RecurringModel::CARD_ON_FILE:
					$recurringParams['recurringProcessingModel'] = static::RECURRING_MODEL_CARD_ON_FILE;
					break;
				default:
					throw new UnexpectedValueException(
						"Unknown recurring processing model $recurringModel"
					);
			}

		}
		return $recurringParams;
	}
}
