<?php

namespace SmashPig\PaymentProviders\Amazon;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;

class RecordPaymentJob implements Runnable {

	/**
	 * @var array
	 */
	public $payload;

	/**
	 * @param array $message normalized for queue
	 * @return array
	 */
	public static function fromAmazonMessage( array $message ) {
		$job = [
			'class' => '\SmashPig\PaymentProviders\Amazon\RecordPaymentJob',
			'payload' => $message
		];
		return $job;
	}

	/**
	 * Add information from pending queue. If not there, look it up via Amazon
	 * Pay API calls.
	 * @throws \SmashPig\Core\SmashPigException
	 */
	public function execute() {
		$merchantReference = $this->payload['order_id'];
		$logger = Logger::getTaggedLogger( "order_id-amazon-$merchantReference" );
		$logger->info(
			"Recording successful capture with order ID '$merchantReference'."
		);

		// Find the details from payments-wiki in the pending database.
		$logger->debug( 'Attempting to locate associated message in pending database' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'amazon', $merchantReference );

		if ( $dbMessage && ( isset( $dbMessage['order_id'] ) ) ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			$queueMessage = $dbMessage;
			unset( $queueMessage['pending_id'] );
			// Add some details from the Amazon message and send it along
			$copyKeys = [
				'date',
				'fee',
				'gateway_txn_id',
				'gateway_status'
			];
			foreach ( $copyKeys as $key ) {
				$queueMessage[$key] = $this->payload[$key];
			}

			QueueWrapper::push( 'donations', $queueMessage );

			// Remove it from the pending database
			$logger->debug( 'Removing donor details message from pending database' );
			$db->deleteMessage( $dbMessage );

		} else {
			// No pending info exists - we need to look up donor details
			$orderReferenceId = $this->payload['order_reference_id'];

			$logger->info(
				'No details in pending DB for order reference ID ' .
				"$orderReferenceId, will call getOrderReferenceDetails",
				$dbMessage
			);
			$api = AmazonApi::get();
			$getDetails = $api->getOrderReferenceDetails( $orderReferenceId );
			$donorDetails = $this->getDonorDetails( $getDetails );
			$queueMessage = $this->payload + $donorDetails;
			unset( $queueMessage['order_reference_id'] );
			QueueWrapper::push( 'donations', $queueMessage );
		}

		return true;
	}

	protected function getDonorDetails( $orderDetails ) {
		$donorDetails = $orderDetails['Buyer'];
		$email = $donorDetails['Email'];
		$name = $donorDetails['Name'];
		$nameParts = preg_split( '/\s+/', $name, 2 ); // janky_split_name
		$fname = $nameParts[0];
		$lname = isset( $nameParts[1] ) ? $nameParts[1] : '';
		$donor = [
			'email' => $email,
			'first_name' => $fname,
			'last_name' => $lname,
		];
		if (
			!empty( $orderDetails['BillingAddress'] ) &&
			!empty( $orderDetails['BillingAddress']['PhysicalAddress'] )
		) {
			$address = $orderDetails['BillingAddress']['PhysicalAddress'];
			$map = [
				'AddressLine1' => 'street_address',
				'PostalCode' => 'postal_code',
				'StateOrRegion' => 'state_province',
				'CountryCode' => 'country',
				'City' => 'city'
			];
			foreach ( $map as $amazonField => $ourField ) {
				if ( !empty( $address[$amazonField] ) ) {
					$donor[$ourField] = $address[$amazonField];
				}
			}
		}
		return $donor;
	}
}
