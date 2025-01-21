<?php namespace SmashPig\Core\DataFiles;

class CsvReader implements \Iterator {
	/**
	 * @var int Maximum length of line to read from the CSV file.
	 */
	protected $maxRowLength = 4096;

	/**
	 * @var resource The pointer to the cvs file.
	 */
	protected $filePointer = null;

	/**
	 * @var array The current element, which will be returned on each iteration.
	 */
	protected $currentElement = null;

	/**
	 * @var int Number of rows read so far.
	 */
	protected $rowCounter = null;

	/**
	 * @var string Delimiter for the csv file.
	 */
	protected $delimiter = null;

	/**
	 * Create an iterative CSV file reader.
	 *
	 * @param string $file Path to file
	 * @param string $delimiter Delimiter to use between CSV fields
	 * @param int $maxRowLength Maximum length a field can take (affects buffering)
	 *
	 * @throws DataFileException on non open-able file.
	 */
	public function __construct( $file, $delimiter = ',', $maxRowLength = 4098 ) {
		$this->filePointer = fopen( $file, 'r' );
		if ( !$this->filePointer ) {
			throw new DataFileException( "Could not open file '{$file}' for reading." );
		}

		$this->delimiter = $delimiter;
		$this->maxRowLength = $maxRowLength;

		// Read the first row
		$this->next();
	}

	/**
	 * Close the file pointer
	 */
	public function __destruct() {
		fclose( $this->filePointer );
	}

	/**
	 * Rewind to the first element.
	 */
	public function rewind() {
		$this->rowCounter = 0;
		rewind( $this->filePointer );
	}

	/**
	 * @return mixed[] The currently buffered CSV row.
	 * @throws DataFileException If no data has been loaded.
	 */
	public function current() {
		return $this->currentElement;
	}

	/**
	 * @return int The current row number
	 */
	public function key() {
		return $this->rowCounter;
	}

	/**
	 * Load the next rows into memory
	 */
	public function next() {
		$this->currentElement = fgetcsv( $this->filePointer, $this->maxRowLength, $this->delimiter );
		$this->rowCounter++;
	}

	/**
	 * Check to see if we have any valid data yet to retrieve
	 * @return bool
	 */
	public function valid() {
		return ( $this->currentElement !== false ) && !feof( $this->filePointer );
	}
}
