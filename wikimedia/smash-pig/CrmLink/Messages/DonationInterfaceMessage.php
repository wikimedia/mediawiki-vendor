<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\DataStores\JsonSerializableObject;

/**
 * Message sent to the pending queue when a payment has been initiated and sent off to the gateway.
 */
class DonationInterfaceMessage extends JsonSerializableObject {
	public $captured = '';
	public $city = '';
	public $contribution_tracking_id = '';
	public $country = '';
	public $currency = '';
	public $date = '';
	public $email = '';
	public $fee = '';
	public $first_name = '';
	public $gateway = '';
	public $gateway_account = '';
	public $gateway_txn_id = '';
	public $gross = '';
	public $language = '';
	public $last_name = '';
	public $middle_name = '';
	public $net = '';
	public $order_id = '';
	public $payment_method = '';
	public $payment_submethod = '';
	public $postal_code = '';
	public $recurring = '';
	public $response = '';
	public $risk_score = '';
	public $state_province = '';
	public $street_address = '';
	public $supplemental_address_1 = '';
	public $user_ip = '';
	public $utm_campaign = '';
	public $utm_medium = '';
	public $utm_source = '';

	/**
	 * @param array $values
	 * @return DonationInterfaceMessage
	 */
	public static function fromValues( $values = [] ) {
		$message = new DonationInterfaceMessage();
		foreach ( $values as $key => $value ) {
			// If we're creating this from a database row with some extra
			// info such as the pending_id, only include the real properties
			if ( property_exists( get_called_class(), $key ) ) {
				$message->$key = $value;
			}
		}
		return $message;
	}
}
