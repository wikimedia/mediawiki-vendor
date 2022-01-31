<?php namespace SmashPig\Core\DataStores;

use PDO;
use PDOStatement;
use SmashPig\Core\Context;

abstract class SmashPigDatabase {

	/**
	 * @var array
	 * key is concrete class name, value is a backing PDO object
	 * We do the silly singleton thing for convenient testing with in-memory
	 * databases that would otherwise not be shared between components.
	 *
	 * Ideally, this would be a scalar variable holding a single PDO object
	 * for each concrete subclass. Unfortunately, in PHP the static member
	 * variable is shared between all subclasses of the class that declares
	 * it, even in an abstract class like this one. See
	 * http://stackoverflow.com/questions/11417681/static-properties-on-base-class-and-inheritance#11418607
	 */
	protected static $dbs = [];

	protected function __construct() {
		$config = Context::get()->getGlobalConfiguration();
		if ( !$this->getDatabase() ) {
			$this->setDatabase( $config->object( $this->getConfigKey() ) );
		}
	}

	public static function get() {
		return new static();
	}

	/**
	 * @return PDO|null
	 */
	public function getDatabase() {
		$className = get_called_class();
		if ( isset( self::$dbs[$className] ) ) {
			return self::$dbs[$className];
		}
		return null;
	}

	protected function setDatabase( PDO $db ) {
		$className = get_called_class();
		self::$dbs[$className] = $db;
	}

	public function createTables() {
		$driver = $this->getDatabase()->getAttribute( PDO::ATTR_DRIVER_NAME );
		foreach ( $this->getTableScriptFiles() as $fileName ) {
			$path = __DIR__ . '/../../Schema/'
				. $driver . '/' . $fileName;
			$this->getDatabase()->exec( file_get_contents( $path ) );
		}
	}

	/**
	 * @return string Key in configuration pointing to backing PDO object
	 */
	abstract protected function getConfigKey(): string;

	/**
	 * @return string Names of files (no directory) containing table creation SQL
	 */
	abstract protected function getTableScriptFiles(): array;

	/**
	 * Build components of a parameterized insert statement
	 *
	 * @param array $record the associative array of values
	 * @return array with two string members, first a concatenated field list,
	 *  then a concatenated list of parameters.
	 */
	protected static function formatInsertParameters( array $record ): array {
		$fields = array_keys( $record );
		$fieldList = implode( ',', $fields );

		// Build a list of parameter names for safe db insert
		// Same as the field list, but each parameter is prefixed with a colon
		$paramList = ':' . implode( ', :', $fields );
		return [ $fieldList, $paramList ];
	}

	/**
	 * Prepares and executes a database command
	 *
	 * @param string $sql parameterized SQL command
	 * @param array $dbRecord associative array of values to bind
	 * @return PDOStatement the executed statement for any fetching
	 * @throws DataStoreException
	 */
	protected function prepareAndExecute( string $sql, array $dbRecord ): PDOStatement {
		$prepared = $this->getDatabase()->prepare( $sql );

		foreach ( $dbRecord as $field => $value ) {
			if ( gettype( $value ) === 'integer' ) {
				$paramType = PDO::PARAM_INT;
			} else {
				$paramType = PDO::PARAM_STR;
			}
			$prepared->bindValue(
				':' . $field,
				$value,
				$paramType
			);
		}

		if ( !$prepared->execute() ) {
			$info = print_r( $prepared->errorInfo(), true );
			throw new DataStoreException( "Failed to execute $sql: $info" );
		}
		return $prepared;
	}
}
