<?php
namespace SmashPig\Core\DataStores;

use PDO;

/**
 * Data store containing fraud scores. Manages a parent table with general
 * information about the fraudiness of a payment attempt including an overall
 * risk score, and a child table with individual fraud filter scores.
 */
class PaymentsFraudDatabase extends SmashPigDatabase {

	const MAX_RISK_SCORE = 100000000;

	/**
	 * Return fraud record for a (gateway, order_id), or null if none is found
	 *
	 * @param string $gatewayName
	 * @param string $orderId
	 * @param bool $withBreakdown
	 * @return array|null Fraud record for a transaction, or null if nothing matches
	 *  If $withBreakdown is requested, the array will contain a 'score_breakdown' key
	 *  whose value is an array whose keys are filter_names and values are risk_scores
	 * @throws DataStoreException
	 */
	public function fetchMessageByGatewayOrderId(
		string $gatewayName,
		string $orderId,
		bool $withBreakdown = false
	): ?array {
		$sql = 'SELECT * FROM payments_fraud
			WHERE gateway = :gateway
		    AND order_id = :order_id
			LIMIT 1';
		$params = [
			'gateway' => $gatewayName,
			'order_id' => $orderId,
		];
		$executed = $this->prepareAndExecute( $sql, $params );
		$row = $executed->fetch( PDO::FETCH_ASSOC );
		// TODO: do we want to rename the fetched 'id' column to 'payments_fraud_id'
		// for the returned message, to parallel what we do in PendingDatabase?
		if ( !$row ) {
			return null;
		}
		// IPs are stored as integers but should be returned as dotted quads
		$row['user_ip'] = long2ip( $row['user_ip'] );
		// Covert date back to unix timestamp
		$row['date'] = !isset( $row['date'] ) ? false : strtotime( $row['date'] );

		if ( $withBreakdown ) {
			$row['score_breakdown'] = [];
			$sql = 'SELECT filter_name, risk_score FROM payments_fraud_breakdown
				WHERE payments_fraud_id = :payments_fraud_id';
			$params = [ 'payments_fraud_id' => $row['id'] ];
			$executed = $this->prepareAndExecute( $sql, $params );
			while ( $breakdownRow = $executed->fetch( PDO::FETCH_ASSOC ) ) {
				$row['score_breakdown'][$breakdownRow['filter_name']] = $breakdownRow['risk_score'];
			}
		}
		return $row;
	}

	/**
	 * @param array $message If an existing row matches the contribution_tracking_id and order_id,
	 *  that row will be updated. Otherwise, inserts a new row in payments_fraud. Also updates or
	 *  inserts rows in the payments_fraud_breakdown table when a score_breakdown key is set.
	 * @throws DataStoreException
	 * @return int primary key of inserted or updated row in payments_fraud table
	 */
	public function storeMessage( array $message ) {
		$breakdown = $message['score_breakdown'] ?? null;
		unset( $message['score_breakdown'] );
		if ( $message['risk_score'] > self::MAX_RISK_SCORE ) {
			$message['risk_score'] = self::MAX_RISK_SCORE;
		}
		// IPs are stored as integers but come over the wire as dotted quads
		$message['user_ip'] = ip2long( $message['user_ip'] );

		// TODO: skip this lookup if the message has a payments_fraud_id key set
		// This lookup is copied from the existing antifraud queue consumer,
		// which is why we are using contribution_tracking_id rather than gateway
		$selectSql = 'SELECT id FROM payments_fraud ' .
			'WHERE contribution_tracking_id = :contribution_tracking_id ' .
			'AND order_id = :order_id LIMIT 1';
		$result = $this->prepareAndExecute( $selectSql, [
			'contribution_tracking_id' => $message['contribution_tracking_id'],
			'order_id' => $message['order_id']
		] );

		$existing = $result->fetch( PDO::FETCH_ASSOC );
		if ( $existing ) {
			// Update an existing row
			$inserting = false;
			$paymentsFraudId = intval( $existing['id'] );
			$sets = [];
			foreach ( array_keys( $message ) as $field ) {
				if ( $field != 'payments_fraud_id' ) {
					$sets[] = "$field = :$field";
				}
			}
			$updateSql = 'UPDATE payments_fraud SET ' .
				implode( ', ', $sets ) .
				" WHERE id = :payments_fraud_id";
			$params = array_merge( $message, [ 'payments_fraud_id' => $paymentsFraudId ] );
			$this->prepareAndExecute( $updateSql, $params );
		} else {
			// Insert a new row
			$inserting = true;
			[ $fieldList, $paramList ] = self::formatInsertParameters(
				$message
			);
			$sql = "INSERT INTO payments_fraud ( $fieldList ) VALUES ( $paramList )";
			$this->prepareAndExecute( $sql, $message );
			$paymentsFraudId = intval( $this->getDatabase()->lastInsertId() );
		}

		if ( $breakdown ) {
			$this->storeBreakdown( $paymentsFraudId, $breakdown, $inserting );
		}
		return $paymentsFraudId;
	}

	protected function getConfigKey(): string {
		return 'data-store/fredge-db';
	}

	protected function getTableScriptFiles(): array {
		return [
			'005_CreatePaymentsFraudTable.sql',
			'006_CreatePaymentsFraudBreakdownTable.sql'
		];
	}

	/**
	 * @param int $paymentsFraudId
	 * @param array $breakdown
	 * @param bool $inserting if true, skip check for existing rows
	 * @throws DataStoreException
	 */
	protected function storeBreakdown( int $paymentsFraudId, array $breakdown, bool $inserting ): void {
		// TODO: add a unique key on (payments_fraud_id, filter_name) so we can do an
		// upsert with ON DUPLICATE KEY UPDATE rather than selecting first
		$existing = [];
		if ( !$inserting ) {
			// We updated the parent row, so we need to check for existing breakdown
			// rows with matching filter_name for the same payments_fraud_id.
			$selectPlaceholders = [];
			$selectParams = [ 'payments_fraud_id' => $paymentsFraudId ];
			$selectIndex = 0;
			foreach ( array_keys( $breakdown ) as $filterName ) {
				$selectPlaceholders[] = ":filter_name_$selectIndex";
				$selectParams["filter_name_$selectIndex"] = $filterName;
				$selectIndex++;
			}
			$selectSql = 'SELECT * FROM payments_fraud_breakdown ' .
				'WHERE payments_fraud_id = :payments_fraud_id ' .
				'AND filter_name in (' . implode( ', ', $selectPlaceholders ) . ')';
			$executed = $this->prepareAndExecute( $selectSql, $selectParams );
			while ( $breakdownRow = $executed->fetch( PDO::FETCH_ASSOC ) ) {
				$existing[$breakdownRow['filter_name']] = [
					'id' => $breakdownRow['id'],
					'risk_score' => $breakdownRow['risk_score']
				];
			}
		}

		// For each filter, determine whether it needs to be inserted or updated.
		// New filters are accumulated so we can do a single insert statement with
		// multiple rows at the end. Updates are executed inside the loop, using the
		// primary key we found from the existing rows query above. When a filter
		// in the message has the same score as an existing row, we just skip it.
		$insertPlaceholders = [];
		$insertParams = [ 'payments_fraud_id' => $paymentsFraudId ];
		$insertIndex = 0;
		foreach ( $breakdown as $filterName => $score ) {
			if ( $score > self::MAX_RISK_SCORE ) {
				$score = self::MAX_RISK_SCORE;
			}
			if ( array_key_exists( $filterName, $existing ) ) {
				// Filter exists in db, update if score has changed
				if ( $existing[$filterName]['risk_score'] != $score ) {
					$updateSql = 'UPDATE payments_fraud_breakdown ' .
						'SET risk_score = :risk_score WHERE id = :id';
					$this->prepareAndExecute( $updateSql, [
						'id' => $existing[$filterName]['id'],
						'risk_score' => $score
					] );
				}
			} else {
				// Not in db yet, add to our lists of insert params and placeholders
				$insertPlaceholders[] = "( :payments_fraud_id, :filter_name_$insertIndex, :risk_score_$insertIndex )";
				$insertParams["filter_name_$insertIndex"] = $filterName;
				$insertParams["risk_score_$insertIndex"] = $score;
				$insertIndex++;
			}
		}

		if ( count( $insertPlaceholders ) > 0 ) {
			$insertSql = 'INSERT INTO payments_fraud_breakdown( payments_fraud_id, filter_name, risk_score ) VALUES ';
			$insertSql .= implode( ', ', $insertPlaceholders );
			$this->prepareAndExecute( $insertSql, $insertParams );
		}
	}
}
