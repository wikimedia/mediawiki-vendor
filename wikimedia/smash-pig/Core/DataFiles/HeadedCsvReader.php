<?php namespace SmashPig\Core\DataFiles;

/**
 * Iteratively reads a CSV file that contains a data header
 *
 * @package SmashPig\Core\DataFiles
 */
class HeadedCsvReader extends CsvReader {
	/** @var string[] */
	protected array $colNames;
	protected array $colIndexes;

	public function __construct( $file, $delimiter = ',', $maxRowLength = 4098, $skipRows = 0 ) {
		parent::__construct( $file, $delimiter, $maxRowLength );

		while ( $skipRows > 0 ) {
			parent::next();
			$skipRows--;
		}
		// Extract the header information
		$this->colNames = parent::current();
		foreach ( $this->colNames as $index => $name ) {
			if ( isset( $this->colIndexes[$name] ) ) {
				throw new DataFileException( "Duplicate column name {$name}!" );
			}
			$this->colIndexes[$name] = $index;
		}
		parent::next();
	}

	/**
	 * Extract the contents of the given column name.
	 *
	 * This is slightly backwards because it did not seem worth the effort
	 * to create a fully functional ArrayObject class to return from current()
	 *
	 * @param string $colName Name of the column to extract
	 * @param string[] $row A row returned from current() **FROM THIS FILE**
	 *
	 * @throws DataFileException if the column name does not exist.
	 * @return string Contents of the column
	 */
	public function extractCol( $colName, &$row ): string {
		if ( !isset( $this->colIndexes[$colName] ) ) {
			throw new DataFileException( "Column name {$colName} not found!" );
		}
		$index = $this->colIndexes[$colName];
		return $row[$index];
	}

	/**
	 * Convenience function to extract a column's value from the current row
	 *
	 * @param string $colName Name of the column to extract
	 *
	 * @throws DataFileException if the column name does not exist.
	 * @return string Contents of the column
	 */
	public function currentCol( $colName ): string {
		return $this->extractCol( $colName, $this->currentElement );
	}

	/**
	 * @return array Associative array where keys are headers and values are
	 *  the values of the corresponding columns from the current row
	 * @throws DataFileException
	 */
	public function currentArray(): array {
		return array_combine( $this->headers(), $this->current() );
	}

	/**
	 * @return string[] CSV file headers in order of columns
	 */
	public function headers(): array {
		return $this->colNames;
	}
}
