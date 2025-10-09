<?php
namespace SmashPig\Core\DataStores;

use PDO;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;
use SmashPig\Core\UtcDate;

/**
 * Data store containing messages waiting to be finalized.
 */
class PendingDatabase extends SmashPigDatabase {

	/**
	 * Check that all required fields exist on a message
	 *
	 * @param array $message
	 * @throws SmashPigException
	 */
	protected function validateMessage( array $message ) {
		if (
			empty( $message['date'] ) ||
			empty( $message['gateway'] ) ||
			( // need at least one transaction ID
				empty( $message['gateway_txn_id'] ) &&
				empty( $message['order_id'] )
			)
		) {
			throw new SmashPigException( 'Message missing required fields' );
		}
	}

	/**
	 * Build and insert a database record from a pending queue message
	 *
	 * @param array $message
	 * @return string ID of message in pending database
	 * @throws DataStoreException
	 * @throws SmashPigException
	 */
	public function storeMessage( array $message ): string {
		$this->validateMessage( $message );

		$dbRecord = [];

		// These fields (and date) have their own columns in the database
		// Copy the values from the message to the record
		$indexedFields = [
			'gateway', 'gateway_account', 'gateway_txn_id', 'order_id', 'payment_method', 'is_resolved'
		];

		foreach ( $indexedFields as $fieldName ) {
			if ( isset( $message[$fieldName] ) ) {
				$dbRecord[$fieldName] = $message[$fieldName];
			}
		}

		$dbRecord['date'] = UtcDate::getUtcDatabaseString( $message['date'] );
		// Dump the whole message into a text column
		$dbRecord['message'] = json_encode( $message );

		if ( isset( $message['pending_id'] ) ) {
			$sql = $this->getUpdateStatement( $dbRecord );
			$dbRecord['id'] = $message['pending_id'];
		} else {
			$sql = $this->getInsertStatement( $dbRecord );
		}
		$this->prepareAndExecute( $sql, $dbRecord );

		return $this->getDatabase()->lastInsertId();
	}

	/**
	 * Return unresolved record matching a (gateway, order_id), or null
	 *
	 * @param string $gatewayName
	 * @param string $orderId
	 * @return array|null Record related to a transaction, or null if nothing matches
	 * @throws DataStoreException
	 */
	public function fetchMessageByGatewayOrderId( string $gatewayName, string $orderId ) {
		$sql = 'select * from pending
			where gateway = :gateway
				and order_id = :order_id
				and is_resolved = 0
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
		return $this->messageFromDbRow( $row );
	}

	/**
	 * Get the oldest unresolved message for a given gateway, and payment_method by date
	 *
	 * @param string $gatewayName
	 * @param array|null $payment_methods
	 * @return array|null Message or null if nothing is found.
	 * @throws DataStoreException
	 */
	public function fetchMessageByGatewayOldest( string $gatewayName, array $payment_methods = [] ) {
		$filterPaymentMethod = "";
		$params = [ 'gateway' => $gatewayName ];
		$placeholder = [];
		for ( $i = 0; $i < count( $payment_methods ); ++$i ) {
			$placeholder[] = ":pm$i";
			$params['pm' . $i] = $payment_methods[$i];
		}
		if ( count( $placeholder ) > 0 ) {
			$filterPaymentMethod .= ' and payment_method in (' . implode( ',', $placeholder ) . ')';
		}
		$sql = 'select * from pending ';
		if ( $this->getDatabase()->getAttribute( PDO::ATTR_DRIVER_NAME ) === 'mysql' ) {
			$sql .= 'FORCE INDEX (idx_pending_resolved_gateway_method_date) ';
		}
		$sql .= 'where gateway = :gateway ' .
			'and is_resolved = 0 ' .
			$filterPaymentMethod .
			' order by date asc limit 1';
		$executed = $this->prepareAndExecute( $sql, $params );
		$row = $executed->fetch( PDO::FETCH_ASSOC );
		if ( !$row ) {
			return null;
		}
		return $this->messageFromDbRow( $row );
	}

	/**
	 * Get the newest N unresolved messages for a given gateway.
	 *
	 * @param string $gatewayName
	 * @param int $limit fetch at most this many messages
	 * @return array|null Messages or null if nothing is found.
	 * @throws DataStoreException
	 */
	public function fetchMessagesByGatewayNewest( string $gatewayName, int $limit = 1 ) {
		$sql = "
			select * from pending
			where gateway = :gateway
			and is_resolved = 0
			order by date desc
			limit $limit";
		$params = [ 'gateway' => $gatewayName ];
		$executed = $this->prepareAndExecute( $sql, $params );
		$rows = $executed->fetchAll( PDO::FETCH_ASSOC );
		if ( !$rows ) {
			return null;
		}
		$messages = array_map( static function ( $row ) {
			return json_decode( $row['message'], true );
		}, $rows );

		return $messages;
	}

	/**
	 * Marks a message as resolved
	 *
	 * Note that we update by (gateway, order_id) internally.
	 *
	 * @param array $message
	 * @throws DataStoreException
	 * @deprecated
	 */
	public function deleteMessage( array $message ) {
		$this->markMessageResolved( $message );
	}

	/**
	 * Marks a message as resolved
	 *
	 * Note that we update by (gateway, order_id) internally.
	 *
	 * @param array $message
	 * @throws DataStoreException
	 */
	public function markMessageResolved( array $message ) {
		if ( !isset( $message['order_id'] ) ) {
			$json = json_encode( $message );
			Logger::warning( "Trying to delete pending message with no order id: $json" );
			return;
		}

		$sql = '
			update pending
			set is_resolved = 1
			where gateway = :gateway
				and order_id = :order_id';
		$params = [
			'gateway' => $message['gateway'],
			'order_id' => $message['order_id'],
		];

		$this->prepareAndExecute( $sql, $params );
	}

	/**
	 * Delete expired messages, optionally by gateway
	 *
	 * @param int $originalDate Oldest date to keep as unix timestamp
	 * @param string|null $gateway
	 * @return int Number of rows deleted
	 * @throws DataStoreException
	 */
	public function deleteOldMessages( int $originalDate, ?string $gateway = null ) {
		$sql = 'DELETE FROM pending WHERE date < :date';
		$params = [
			'date' => UtcDate::getUtcDatabaseString( $originalDate ),
		];
		if ( $gateway ) {
			$sql .= ' AND gateway = :gateway';
			$params['gateway'] = $gateway;
		}
		$executed = $this->prepareAndExecute( $sql, $params );
		return $executed->rowCount();
	}

	/**
	 * Mark older messages as resolved, optionally by gateway
	 *
	 * @param int $originalDate Oldest date to leave unresolved as unix timestamp
	 * @param string|null $gateway
	 * @return int Number of rows marked resolved
	 * @throws DataStoreException
	 */
	public function resolveOldMessages( int $originalDate, ?string $gateway = null ) {
		$sql = 'UPDATE pending SET is_resolved = 1 WHERE date < :date';
		$params = [
			'date' => UtcDate::getUtcDatabaseString( $originalDate ),
		];
		if ( $gateway ) {
			$sql .= ' AND gateway = :gateway';
			$params['gateway'] = $gateway;
		}
		$executed = $this->prepareAndExecute( $sql, $params );
		return $executed->rowCount();
	}

	/**
	 * Parse a database row and return the normalized message.
	 *
	 * @param array $row An associative array whose keys are raw pending table columns
	 * @return array The decoded message from the `message` column, plus pending_id from `id`
	 */
	protected function messageFromDbRow( array $row ): array {
		$message = json_decode( $row['message'], true );
		$message['pending_id'] = $row['id'];
		return $message;
	}

	/**
	 * @param array $record
	 * @return string SQL to insert a pending record, with parameters
	 */
	protected function getInsertStatement( array $record ): string {
		[ $fieldList, $paramList ] = self::formatInsertParameters(
			$record
		);

		$insert = "INSERT INTO pending ( $fieldList ) VALUES ( $paramList )";
		return $insert;
	}

	/**
	 * @param array $record
	 * @return string SQL to update a pending record, with parameters
	 */
	protected function getUpdateStatement( array $record ): string {
		$sets = [];
		foreach ( array_keys( $record ) as $field ) {
			$sets[] = "$field = :$field";
		}
		$update = 'UPDATE pending SET ' .
			implode( ',', $sets ) .
			' WHERE id = :id';
		return $update;
	}

	protected function getConfigKey(): string {
		return 'data-store/pending-db';
	}

	protected function getTableScriptFiles(): array {
		return [ '001_CreatePendingTable.sql' ];
	}
}
