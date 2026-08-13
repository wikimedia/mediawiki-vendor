<?php
declare( strict_types = 1 );

/**
 * Pager.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Pager` class, responsible for managing
 * the behavior and properties of the corresponding component.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Component;

use InvalidArgumentException;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Renderer\PagerRenderer;

/**
 * Pager
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Pager extends Component {
	private string $id = '';
	/**
	 * Valid positions for pagination controls ('top', 'bottom', or 'both').
	 */
	private const TABLE_PAGINATION_POSITIONS = [
		'top',
		'bottom',
		'both',
	];

	public function __construct(
		PagerRenderer $renderer,
		private array $paginationSizeOptions,
		private int $paginationSizeDefault,
		private int $totalPages,
		private int $totalResults,
		private string $position,
		private int $limit,
		private ?int $currentOffset,
		private ?int $nextOffset,
		private ?int $prevOffset,
		private ?int $firstOffset,
		private ?int $lastOffset,
		private int $startOrdinal,
		private int $endOrdinal,
		private array $attributes,
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the Pager's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the pager element, which is used
	 * for identifying the pager in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Pager.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Determine if the first button should be disabled.
	 *
	 * This method checks whether the first button should be disabled based on the current page.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the first button should be disabled, false otherwise.
	 */
	public function isFirstDisabled(): bool {
		return $this->firstOffset == null && $this->prevOffset == null;
	}

	/**
	 * Determine if the previous button should be disabled.
	 *
	 * This method checks whether the previous button should be disabled based on the current page.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the previous button should be disabled, false otherwise.
	 */
	public function isPrevDisabled(): bool {
		return $this->prevOffset == null;
	}

	/**
	 * Determine if the next button should be disabled.
	 *
	 * This method checks whether the next button should be disabled based on the total results and the current page.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the next button should be disabled, false otherwise.
	 */
	public function isNextDisabled(): bool {
		return $this->nextOffset == null || $this->currentOffset == $this->lastOffset;
	}

	/**
	 * Determine if the last button should be disabled.
	 *
	 * This method checks whether the last button should be disabled based on the indeterminate state.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the last button should be disabled, false otherwise.
	 */
	public function isLastDisabled(): bool {
		return $this->nextOffset == null || $this->currentOffset == $this->lastOffset;
	}

	/**
	 * Get the current offset for the pager.
	 *
	 * This method returns the current offset value, which determines the
	 * starting point for the data on the current page. In cursor-based
	 * pagination, this offset is usually a timestamp or unique identifier.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the current page.
	 */
	public function getCurrentOffset(): ?int {
		return $this->currentOffset;
	}

	/**
	 * Get the offset for the first page.
	 *
	 * This method returns the offset for the first page in cursor-based
	 * pagination. The first page offset usually represents the earliest
	 * timestamp or unique identifier in the dataset.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the first page, or null if not set.
	 */
	public function getFirstOffset(): ?int {
		return $this->firstOffset;
	}

	/**
	 * Get the offset for the previous page.
	 *
	 * This method returns the offset for the previous page in cursor-based
	 * pagination. The previous page offset is typically the timestamp or
	 * unique identifier of the first item in the current page.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the previous page, or null if not set.
	 */
	public function getPrevOffset(): ?int {
		return $this->prevOffset;
	}

	/**
	 * Get the offset for the next page.
	 *
	 * This method returns the offset for the next page in cursor-based
	 * pagination. The next page offset is typically the timestamp or
	 * unique identifier of the last item on the current page.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the next page, or null if not set.
	 */
	public function getNextOffset(): ?int {
		return $this->nextOffset;
	}

	/**
	 * Get the offset for the last page.
	 *
	 * This method returns the offset for the last page in cursor-based
	 * pagination. The last page offset typically represents the timestamp
	 * or unique identifier of the last item in the dataset.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the last page, or null if not set.
	 */
	public function getLastOffset(): ?int {
		return $this->lastOffset;
	}

	/**
	 * Get the start ordinal for the current page.
	 *
	 * @since 0.1.0
	 * @return int The start ordinal.
	 */
	public function getStartOrdinal(): int {
		return $this->startOrdinal;
	}

	/**
	 * Get the end ordinal for the current page.
	 *
	 * @since 0.1.0
	 * @return int The end ordinal.
	 */
	public function getEndOrdinal(): int {
		return $this->endOrdinal;
	}

	/**
	 * Get the total number of pages.
	 *
	 * This method returns the total number of pages available based on the dataset.
	 *
	 * @since 0.1.0
	 * @return int The total number of pages.
	 */
	public function getTotalPages(): int {
		return $this->totalPages;
	}

	/**
	 * Get the total number of results.
	 *
	 * This method returns the total number of results in the dataset.
	 *
	 * @since 0.1.0
	 * @return int The total number of results.
	 */
	public function getTotalResults(): int {
		return $this->totalResults;
	}

	/**
	 * Get the limit for the pager.
	 *
	 * This method returns the number of results to be displayed per page.
	 *
	 * @since 0.1.0
	 * @return int The number of results per page.
	 */
	public function getLimit(): int {
		return $this->limit;
	}

	/**
	 * Get the position of the pagination controls.
	 *
	 * This method returns the position where the pagination controls are displayed. Valid positions
	 * are 'top', 'bottom', or 'both'.
	 *
	 * @since 0.1.0
	 * @return string The position of the pagination controls.
	 */
	public function getPosition(): string {
		return $this->position;
	}

	/**
	 * Get the pagination size options.
	 *
	 * This method returns the available options for the number of results displayed per page.
	 * Users can select from these options in a dropdown.
	 *
	 * @since 0.1.0
	 * @return array The array of pagination size options.
	 */
	public function getPaginationSizeOptions(): array {
		return $this->paginationSizeOptions;
	}

	/**
	 * Get the default pagination size.
	 *
	 * This method returns the default number of rows displayed per page.
	 *
	 * @since 0.1.0
	 * @return int The default pagination size.
	 */
	public function getPaginationSizeDefault(): int {
		return $this->paginationSizeDefault;
	}

	/**
	 * Get the additional HTML attributes for the outer `<div>` element.
	 *
	 * This method returns an associative array of HTML attributes that are applied to the outer `<div>` element of the
	 * progress bar. These attributes can include `id`, `data-*`, `aria-*`, or any other valid HTML attributes.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the Pager's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the Pager element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the total number of pages.
	 *
	 * The total number of pages available based on the dataset.
	 *
	 * @since 0.1.0
	 * @param int $totalPages The total number of pages.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setTotalPages( int $totalPages ): self {
		$this->totalPages = $totalPages;

		return $this;
	}

	/**
	 * Set the total number of results.
	 *
	 * The total number of results in the dataset.
	 *
	 * @since 0.1.0
	 * @param int $totalResults The total number of results.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setTotalResults( int $totalResults ): self {
		$this->totalResults = $totalResults;

		return $this;
	}

	/**
	 * Set the limit for the pager.
	 *
	 * The number of results to be displayed per page. The limit must be at least 1.
	 *
	 * @since 0.1.0
	 * @param int $limit The number of results per page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setLimit( int $limit ): self {
		if ( $limit < 1 ) {
			throw new InvalidArgumentException( 'The limit must be at least 1.' );
		}

		$this->limit = $limit;

		return $this;
	}

	/**
	 * Set the current offset for the pager.
	 *
	 * This method sets the current offset, typically a timestamp or unique
	 * identifier, for cursor-based pagination. The offset represents the
	 * position in the dataset from which to start fetching the next page
	 * of results.
	 *
	 * Example usage:
	 *
	 *     $pager->setCurrentOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $currentOffset The offset value (usually a timestamp).
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setCurrentOffset( ?int $currentOffset ): self {
		$this->currentOffset = $currentOffset;

		return $this;
	}

	/**
	 * Set the offset for the first page.
	 *
	 * This method sets the offset for the first page in cursor-based
	 * pagination. It usually represents the earliest timestamp in the
	 * dataset.
	 *
	 * Example usage:
	 *
	 *     $pager->setFirstOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $firstOffset The offset for the first page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setFirstOffset( ?int $firstOffset ): self {
		$this->firstOffset = $firstOffset;

		return $this;
	}

	/**
	 * Set the offset for the previous page.
	 *
	 * This method sets the offset for the previous page in cursor-based
	 * pagination. The offset is typically the timestamp of the first
	 * item in the current page.
	 *
	 * Example usage:
	 *
	 *     $pager->setPrevOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $prevOffset The offset for the previous page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setPrevOffset( ?int $prevOffset ): self {
		$this->prevOffset = $prevOffset;

		return $this;
	}

	/**
	 * Set the offset for the next page.
	 *
	 * This method sets the offset for the next page in cursor-based
	 * pagination. It is typically the timestamp of the last item on the
	 * current page.
	 *
	 * Example usage:
	 *
	 *     $pager->setNextOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $nextOffset The offset for the next page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setNextOffset( ?int $nextOffset ): self {
		$this->nextOffset = $nextOffset;

		return $this;
	}

	/**
	 * Set the offset for the last page.
	 *
	 * This method sets the offset for the last page in cursor-based
	 * pagination. It typically represents the timestamp of the last
	 * item in the dataset.
	 *
	 * Example usage:
	 *
	 *     $pager->setLastOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $lastOffset The offset for the last page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setLastOffset( ?int $lastOffset ): self {
		$this->lastOffset = $lastOffset;

		return $this;
	}

	/**
	 * Set the start and end ordinals for the current page.
	 *
	 * This method defines the range of items (ordinals) displayed on the
	 * current page of results. The ordinals represent the 1-based index
	 * of the first and last items shown on the page.
	 *
	 * Ordinals are typically determined based on the current page number
	 * and the limit, which is the number of items per page. The `startOrdinal`
	 * specifies the index of the first item on the page, while `endOrdinal`
	 * specifies the index of the last item. This ensures accurate display
	 * of the current page's item range.
	 *
	 * **Tip**: When working with cursor-based pagination (e.g., based on
	 * timestamps), ordinals can be calculated by determining the position
	 * of the current offset within the dataset. By tracking the relative
	 * position of items using their timestamps, the starting and ending
	 * ordinal values for each page can be derived.
	 *
	 * Example usage:
	 *
	 *     $pager->setOrdinals(6, 10);
	 *
	 * @since 0.1.0
	 * @param int $startOrdinal The 1-based index of the first item displayed.
	 * @param int $endOrdinal The 1-based index of the last item displayed.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setOrdinals( int $startOrdinal, int $endOrdinal ): self {
		$this->startOrdinal = $startOrdinal;
		$this->endOrdinal = $endOrdinal;

		return $this;
	}

	/**
	 * Set the position for the pager.
	 *
	 * This method specifies where the pagination controls should appear.
	 * Valid positions are 'top', 'bottom', or 'both'.
	 *
	 * Example usage:
	 *
	 *     $pager->setPosition('top');
	 *
	 * @since 0.1.0
	 * @param string $position The position of the pagination controls ('top', 'bottom', or 'both').
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setPosition( string $position ): self {
		if ( !in_array( $position, self::TABLE_PAGINATION_POSITIONS, true ) ) {
			throw new InvalidArgumentException( "Invalid pagination position: $position" );
		}
		$this->position = $position;

		return $this;
	}

	/**
	 * Set the pagination size options.
	 *
	 * This method defines the available options for the number of results displayed per page.
	 * Users can select from these options in a dropdown, and the selected value will control
	 * how many items are displayed on each page.
	 *
	 * Example usage:
	 *
	 *     $pager->setPaginationSizeOptions([10, 20, 50]);
	 *
	 * @since 0.1.0
	 * @param array $paginationSizeOptions The array of pagination size options.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setPaginationSizeOptions( array $paginationSizeOptions ): self {
		if ( !$paginationSizeOptions ) {
			throw new InvalidArgumentException( 'Pagination size options cannot be empty.' );
		}
		$this->paginationSizeOptions = $paginationSizeOptions;

		return $this;
	}

	/**
	 * Set the default pagination size.
	 *
	 * This method specifies the default number of rows displayed per page.
	 *
	 * @since 0.1.0
	 * @param int $paginationSizeDefault The default number of rows per page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setPaginationSizeDefault( int $paginationSizeDefault ): self {
		if ( !in_array( $paginationSizeDefault, $this->paginationSizeOptions, true ) ) {
			throw new InvalidArgumentException( 'Default pagination size must be one of the pagination size options.' );
		}
		$this->paginationSizeDefault = $paginationSizeDefault;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer `<div>` element.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<div>` element of the pager
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $pager->setAttributes( [ 'class' => 'my-pager' ] );
	 *
	 * @since 0.8.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
