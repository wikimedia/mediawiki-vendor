<?php namespace SmashPig\Core\DataFiles;

class CsvReader implements \Iterator {
	/**
	 * @var int Maximum length of line to read from the CSV file.
	 */
	protected int $maxRowLength = 4096;

	/**
	 * @var resource The pointer to the cvs file.
	 */
	protected $filePointer = null;

	/**
	 * @var array|false|null The current element, which will be returned on each iteration.
	 */
	protected mixed $currentElement = null;

	/**
	 * @var int Number of rows read so far.
	 */
	protected int $rowCounter = 0;

	/**
	 * @var string|null Delimiter for the csv file.
	 */
	protected ?string $delimiter = null;

	/**
	 * Create an iterative CSV file reader.
	 *
	 * @param string $file Path to file
	 * @param string $delimiter Delimiter to use between CSV fields
	 * @param int $maxRowLength Maximum length a field can take (affects buffering)
	 *
	 * @throws DataFileException on non open-able file.
	 */
	public function __construct( string $file, string $delimiter = ',', int $maxRowLength = 4098 ) {
		$this->filePointer = fopen( $file, 'r' );
		if ( !$this->filePointer ) {
			throw new DataFileException( "Could not open file '$file' for reading." );
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
	public function rewind(): void {
		$this->rowCounter = 0;
		rewind( $this->filePointer );
	}

	/**
	 * @return mixed The currently buffered CSV row.
	 */
	public function current(): mixed {
		return $this->currentElement;
	}

	/**
	 * @return int The current row number
	 */
	public function key(): int {
		return $this->rowCounter;
	}

	/**
	 * Load the next rows into memory
	 */
	public function next(): void {
		$this->currentElement = fgetcsv( $this->filePointer, $this->maxRowLength, $this->delimiter );
		$this->rowCounter++;
	}

	/**
	 * Check to see if we have any valid data yet to retrieve
	 * @return bool
	 */
	public function valid(): bool {
		return ( $this->currentElement !== false ) && !feof( $this->filePointer );
	}
}
