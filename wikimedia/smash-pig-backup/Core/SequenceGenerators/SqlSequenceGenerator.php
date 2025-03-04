<?php

namespace SmashPig\Core\SequenceGenerators;

use PDO;
use SmashPig\Core\SmashPigException;

class SqlSequenceGenerator implements ISequenceGenerator {

	protected $name;
	protected $connection_string;
	protected $db_user;
	protected $db_password;
	protected $pdo_options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ];

	/**
	 * @var PDO|null
	 */
	protected $connection = null;
	protected $db_table = '_sequence';

	/**
	 * @param array $options
	 * @throws SmashPigException
	 */
	public function __construct( $options ) {
		$this->name = $options['sequence'];
		if ( empty( $options['connection_string'] ) ) {
			throw new SmashPigException( 'No servers specified' );
		} else {
			$this->connection_string = $options['connection_string'];
		}
		if ( !empty( $options['db_user'] ) ) {
			$this->db_user = $options['db_user'];
		}
		if ( !empty( $options['db_password'] ) ) {
			$this->db_password = $options['db_password'];
		}
		if ( !empty( $options['db_table'] ) ) {
			$this->db_password = $options['db_table'];
		}
		if ( !empty( $options['pdo_options'] ) && is_array( $options['pdo_options'] ) ) {
			// Use + operator instead of array_merge to preserve integer keys
			$this->pdo_options = $options['pdo_options'] + $this->pdo_options;
		}
	}

	/**
	 * Get the next number in the sequence
	 *
	 * @return int
	 */
	public function getNext() {
		if ( !$this->connection ) {
			$this->connect();
		}
		$this->connection->beginTransaction();
		try {
			$sql = "SELECT sequence_number FROM {$this->db_table} WHERE sequence_name = ?";
			$prepared = $this->connection->prepare( $sql );
			$prepared->bindValue( 1, $this->name );
			$prepared->execute();
			// If we've gotten this far, the table exists. We still have to
			// check for a row for our sequence.
			$foundRow = $prepared->fetch( PDO::FETCH_ASSOC );
			if ( $foundRow === false ) {
				$exists = false;
			} else {
				$exists = true;
				$result = $foundRow['sequence_number'] + 1;
			}
		} catch ( \Exception $ex ) {
			$this->createTable();
			$exists = false;
		}
		if ( $exists ) {
			// Update existing row
			$sql = "UPDATE {$this->db_table} SET sequence_number = sequence_number + 1 WHERE sequence_name = ?";
		} else {
			// Create a new row starting at 1
			$sql = "INSERT INTO {$this->db_table} (sequence_name, sequence_number) VALUES( ?, 1 )";
			$result = 1;
		}
		$prepared = $this->connection->prepare( $sql );
		$prepared->bindValue( 1, $this->name );
		$prepared->execute();
		$this->connection->commit();

		return $result;
	}

	public function createTable() {
		if ( !$this->connection ) {
			$this->connect();
		}
		$sql = "CREATE TABLE {$this->db_table} (sequence_name VARCHAR(255) PRIMARY KEY, sequence_number INT)";
		$prepared = $this->connection->prepare( $sql );
		$prepared->execute();
	}

	public function initializeSequence( $startNumber = 0 ) {
		if ( !$this->connection ) {
			$this->connect();
		}
		$sql = "DELETE FROM {$this->db_table} WHERE sequence_name = ?";
		try {
			$prepared = $this->connection->prepare( $sql );
			$prepared->bindValue( 1, $this->name );
			$prepared->execute();
		} catch ( \Exception $ex ) {
			$this->createTable();
		}
		$sql = "INSERT INTO {$this->db_table} (sequence_name, sequence_number) VALUES( ?, ? )";
		$prepared = $this->connection->prepare( $sql );
		$prepared->bindValue( 1, $this->name );
		$prepared->bindValue( 2, $startNumber, PDO::PARAM_INT );
		$prepared->execute();
	}

	protected function connect() {
		$this->connection = new PDO(
			$this->connection_string, $this->db_user, $this->db_password, $this->pdo_options
		);
	}
}
