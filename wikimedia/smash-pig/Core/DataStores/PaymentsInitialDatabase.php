<?php
namespace SmashPig\Core\DataStores;

use PDO;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;

/**
 * Data store containing finalized messages.
 */
class PaymentsInitialDatabase extends SmashPigDatabase {

	/**
	 * Return true if the message already exists in the payments-init table,
	 * is marked as having failed, and is not up for review.
	 *
	 * @param array $message Payments initial message
	 *    FIXME: Or pass ID parameters explicitly and call this
	 *    isTransactionFinalizedByGatewayOrderId??
	 * @return bool
	 * @throws DataStoreException
	 */
	public function isTransactionFailed( array $message ): bool {
		$message = $this->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );
		if ( $message === null ) {
			return false;
		}
		return self::isMessageFailed( $message );
	}

	/**
	 * This function is used to determine whether to delete (or not store)
	 * a corresponding row in the pending table.
	 *
	 * @param array $message a payments-init message
	 * @return bool true if the message indicates that the payment has been
	 *  definitively failed and won't come up again, and that we should delete
	 *  the corresponding pending row.
	 */
	public static function isMessageFailed( array $message ): bool {
		if ( !in_array(
			$message['payments_final_status'],
			[ FinalStatus::FAILED, FinalStatus::CANCELLED ]
		) ) {
			// Status is not failed, no reason to delete the pending row
			return false;
		}
		if (
			$message['validation_action'] === ValidationAction::REVIEW ||
			(
				$message['validation_action'] === ValidationAction::REJECT &&
				in_array( $message['gateway'], [ 'adyen', 'ingenico' ] )
			)
		) {
			// Leave the pending message for potential capture by the pending
			// transaction rectifier or manual review - for any gateway, REVIEW
			// status can be captured. FIXME: Our Ingenico front-end code also
			// treats REJECT status this way, so we should leave those rows as
			// well, same as adyen, only get process and reject, but our pending resolver
			// will process this, so adyen reject should stay
			return false;
		}
		// payments_final_status is either FAILED or CANCELLED, and it's not
		// because of our fraud filters. Safe to delete any pending row.
		return true;
	}

	/**
	 * Return record matching a (gateway, order_id), or null if none is found
	 *
	 * @param string $gatewayName
	 * @param string $orderId
	 * @return array|null Record related to a transaction, or null if nothing matches
	 * @throws DataStoreException
	 */
	public function fetchMessageByGatewayOrderId( string $gatewayName, string $orderId ) {
		$sql = 'select * from payments_initial
			where gateway = :gateway
				and order_id = :order_id
			limit 1';
		$params = [
			'gateway' => $gatewayName,
			'order_id' => $orderId,
		];
		$executed = $this->prepareAndExecute( $sql, $params );
		$row = $executed->fetch( PDO::FETCH_ASSOC );
		if ( !$row ) {
			return null;
		}
		return $row;
	}

	/**
	 * @param array $message
	 * @return string The ID of the inserted row
	 * @throws DataStoreException
	 */
	public function storeMessage( array $message ): string {
		[ $fieldList, $paramList ] = self::formatInsertParameters(
			$message
		);

		$sql = "INSERT INTO payments_initial ( $fieldList ) VALUES ( $paramList )";
		$this->prepareAndExecute( $sql, $message );

		return $this->getDatabase()->lastInsertId();
	}

	protected function getConfigKey(): string {
		return 'data-store/fredge-db';
	}

	protected function getTableScriptFiles(): array {
		return [ '003_CreatePaymentsInitialTable.sql' ];
	}
}
