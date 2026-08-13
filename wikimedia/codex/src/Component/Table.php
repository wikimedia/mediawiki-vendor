<?php
declare( strict_types = 1 );

/**
 * Table.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Table` class, responsible for managing
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

use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Renderer\TableRenderer;

/**
 * Table
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Table extends Component {
	private string $id = '';

	/**
	 * Sort direction for ascending order.
	 */
	public const SORT_ASCENDING = 'asc';

	/**
	 * Sort direction for descending order.
	 */
	public const SORT_DESCENDING = 'desc';

	public function __construct(
		TableRenderer $renderer,
		private string $caption,
		private bool $hideCaption,
		private array $columns,
		private array $data,
		private bool $useRowHeaders,
		private string|HtmlSnippet|null $headerContent,
		private array $sort,
		private ?string $currentSortColumn,
		private string $currentSortDirection,
		private bool $showVerticalBorders,
		private bool $paginate,
		private int $totalRows,
		private string $paginationPosition,
		private ?Pager $pager,
		private string|HtmlSnippet|null $footer,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the HTML ID for the table.
	 *
	 * This method returns the HTML `id` attribute value for the table element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the table.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the opposite sort direction.
	 *
	 * This method returns the opposite of the current sort direction.
	 *
	 * @since 0.1.0
	 * @param string $direction The current sort direction ('asc' or 'desc').
	 * @return string The opposite sort direction.
	 */
	public function oppositeSort( string $direction ): string {
		return $direction === self::SORT_ASCENDING ? self::SORT_DESCENDING : self::SORT_ASCENDING;
	}

	/**
	 * Get whether row headers are used.
	 *
	 * This method returns a boolean value indicating whether the first column of the table is treated as row headers.
	 * Row headers are useful for accessibility and provide additional context for each row.
	 *
	 * @since 0.1.0
	 * @return bool True if row headers are used, false otherwise.
	 */
	public function getUseRowHeaders(): bool {
		return $this->useRowHeaders;
	}

	/**
	 * Check if vertical borders are shown between columns.
	 *
	 * This method returns a boolean value indicating whether vertical borders are displayed between columns in the
	 * table.
	 *
	 * @since 0.1.0
	 * @return bool True if vertical borders are displayed, false otherwise.
	 */
	public function getShowVerticalBorders(): bool {
		return $this->showVerticalBorders;
	}

	/**
	 * Get the Pager instance for the table.
	 *
	 * This method returns the Pager instance if it is set, which provides pagination controls for the table.
	 *
	 * @since 0.1.0
	 * @return Pager|null Returns the Pager instance or null if not set.
	 */
	public function getPager(): ?Pager {
		return $this->pager;
	}

	/**
	 * Get the position of the pagination controls.
	 *
	 * This method returns the position of the pagination controls, which can be 'top', 'bottom', or 'both'.
	 *
	 * @since 0.1.0
	 * @return string The position of the pagination controls ('top', 'bottom', or 'both').
	 */
	public function getPaginationPosition(): string {
		return $this->paginationPosition;
	}

	/**
	 * Get the total number of rows.
	 *
	 * This method returns the total number of rows in the table, which is used for pagination and display purposes.
	 *
	 * @since 0.1.0
	 * @return int The total number of rows in the table.
	 */
	public function getTotalRows(): int {
		return $this->totalRows;
	}

	/**
	 * Get the footer content for the table.
	 *
	 * This method returns the footer content if it is set, which can contain additional information or actions related
	 * to the table.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet|null The footer content or null if not set.
	 */
	public function getFooter(): string|HtmlSnippet|null {
		return $this->footer;
	}

	/**
	 * Get the header content for the table.
	 *
	 * This method returns the custom content for the table's header if it is set, such as actions or additional text.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet|null The header content or null if not set.
	 */
	public function getHeaderContent(): string|HtmlSnippet|null {
		return $this->headerContent;
	}

	/**
	 * Get the caption for the table.
	 *
	 * This method returns the caption text that provides a description of the table's contents and purpose.
	 *
	 * @since 0.1.0
	 * @return string The caption text for the table.
	 */
	public function getCaption(): string {
		return $this->caption;
	}

	/**
	 * Check if the caption is hidden.
	 *
	 * This method returns a boolean value indicating whether the caption is visually hidden but still accessible to
	 * screen readers.
	 *
	 * @since 0.1.0
	 * @return bool True if the caption is hidden, false otherwise.
	 */
	public function getHideCaption(): bool {
		return $this->hideCaption;
	}

	/**
	 * Get the columns for the table.
	 *
	 * This method returns an array of columns defined for the table, where each column is an associative array
	 * containing column attributes.
	 *
	 * @since 0.1.0
	 * @return array The array of columns defined for the table.
	 */
	public function getColumns(): array {
		return $this->columns;
	}

	/**
	 * Get the data for the table.
	 *
	 * This method returns the array of data to be displayed in the table, where each row is an associative array with
	 * keys matching column IDs.
	 *
	 * @since 0.1.0
	 * @return array The array of data for the table.
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * Get the current sort column.
	 *
	 * This method returns the ID of the column currently used for sorting the table data.
	 *
	 * @since 0.1.0
	 * @return ?string The ID of the column used for sorting.
	 */
	public function getCurrentSortColumn(): ?string {
		return $this->currentSortColumn;
	}

	/**
	 * Get the current sort direction.
	 *
	 * This method returns the current sort direction, which can be either 'asc' for ascending or 'desc' for descending.
	 *
	 * @since 0.1.0
	 * @return string The current sort direction ('asc' or 'desc').
	 */
	public function getCurrentSortDirection(): string {
		return $this->currentSortDirection;
	}

	/**
	 * Get additional HTML attributes for the table element.
	 *
	 * This method returns an associative array of custom HTML attributes applied to the `<table>` element.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the Table HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the Table element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the caption for the table.
	 *
	 * The caption provides a description of the table's contents and purpose. It is essential for accessibility
	 * as it helps screen readers convey the context of the table to users. To visually hide the caption while
	 * keeping it accessible, use the `setHideCaption()` method.
	 *
	 * Example usage:
	 *
	 *     $table->setCaption('Article List');
	 *
	 * @since 0.1.0
	 * @param string $caption The caption text to be displayed above the table.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setCaption( string $caption ): self {
		$this->caption = $caption;

		return $this;
	}

	/**
	 * Set whether to hide the caption.
	 *
	 * If set to true, the caption will be visually hidden but still accessible to screen readers.
	 *
	 * @since 0.1.0
	 * @param bool $hideCaption Indicates if the caption should be visually hidden.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setHideCaption( bool $hideCaption ): self {
		$this->hideCaption = $hideCaption;

		return $this;
	}

	/**
	 * Set the columns for the table.
	 *
	 * Each column is defined by an associative array with attributes such as 'id', 'label', 'sortable', etc.
	 * The 'label' can be a string (plain text) or an HtmlSnippet object (raw HTML).
	 *
	 * Example usage:
	 *
	 *     $table->setColumns([
	 *         ['id' => 'title', 'label' => 'Title', 'sortable' => true],
	 *         ['id' => 'creation_date', 'label' => 'Creation Date', 'sortable' => false]
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $columns An array of columns, where each column is an associative array containing column
	 *                       attributes.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setColumns( array $columns ): self {
		$this->columns = $columns;

		return $this;
	}

	/**
	 * Set the data for the table.
	 *
	 * The data array should correspond to the columns defined. Each row is an associative array where keys match
	 * column IDs. The values can be strings (plain text) or HtmlSnippet objects (raw HTML).
	 *
	 * Example usage:
	 *
	 *     $table->setData([
	 *         ['title' => 'Mercury', 'creation_date' => '2024-01-01'],
	 *         ['title' => 'Venus', 'creation_date' => '2024-01-02'],
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $data An array of data to be displayed in the table, where each row is an associative array with
	 *                    keys matching column IDs.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setData( array $data ): self {
		$this->data = $data;

		return $this;
	}

	/**
	 * Set whether to use row headers.
	 *
	 * If enabled, the first column of the table will be treated as row headers. This is useful for accessibility
	 * and to provide additional context for each row.
	 *
	 * @since 0.1.0
	 * @param bool $useRowHeaders Indicates if row headers should be used.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setUseRowHeaders( bool $useRowHeaders ): self {
		$this->useRowHeaders = $useRowHeaders;

		return $this;
	}

	/**
	 * Set whether to show vertical borders between columns.
	 *
	 * Vertical borders can help distinguish between columns, especially in tables with many columns.
	 *
	 * @since 0.1.0
	 * @param bool $showVerticalBorders Indicates if vertical borders should be displayed between columns.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setShowVerticalBorders( bool $showVerticalBorders ): self {
		$this->showVerticalBorders = $showVerticalBorders;

		return $this;
	}

	/**
	 * Set the sort order for the table.
	 *
	 * This method defines the initial sort order for the table. The array should contain
	 * column IDs as keys and sort directions ('asc' or 'desc') as values.
	 *
	 * Example usage:
	 *
	 *     $table->setSort([
	 *         'column1' => 'asc',
	 *         'column2' => 'desc'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $sort An associative array of column IDs and their respective sort directions ('asc' or 'desc').
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setSort( array $sort ): self {
		$this->sort = $sort;

		return $this;
	}

	/**
	 * Set whether the table should be paginated.
	 *
	 * If enabled, pagination controls will be added to the table, allowing users to navigate through multiple pages of
	 * data.
	 *
	 * @since 0.1.0
	 * @param bool $paginate Indicates if the table should be paginated.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setPaginate( bool $paginate ): self {
		$this->paginate = $paginate;

		return $this;
	}

	/**
	 * Set the total number of rows in the table.
	 *
	 * This value is used in conjunction with pagination to calculate the total number of pages and to display the
	 * current range of rows.
	 *
	 * @since 0.1.0
	 * @param int $totalRows The total number of rows in the table.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setTotalRows( int $totalRows ): self {
		$this->totalRows = $totalRows;

		return $this;
	}

	/**
	 * Set the position of the pagination controls.
	 *
	 * The pagination controls can be displayed at the top, bottom, or both top and bottom of the table.
	 *
	 * @since 0.1.0
	 * @param string $paginationPosition The position of the pagination controls ('top', 'bottom', 'both').
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setPaginationPosition( string $paginationPosition ): self {
		$this->paginationPosition = $paginationPosition;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the table element.
	 *
	 * This method allows custom HTML attributes to be added to the `<table>` element, such as `id`, `class`,
	 * or `data-*` attributes. These attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $table->setAttributes(['class' => 'custom-table-class', 'data-info' => 'additional-info']);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes to be added to the `<table>` element.
	 *
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set the Pager instance for the table.
	 *
	 * The Pager instance provides pagination controls for the table. If set, pagination controls will be rendered
	 * according to the settings.
	 *
	 * @since 0.1.0
	 * @param Pager $pager The Pager instance.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setPager( Pager $pager ): self {
		$this->pager = $pager;

		return $this;
	}

	/**
	 * Set the footer content for the table.
	 *
	 * The footer is an optional section that can contain additional information or actions related to the table.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $footer The footer content.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setFooter( string|HtmlSnippet $footer ): self {
		$this->footer = $footer;

		return $this;
	}

	/**
	 * Set the header content for the table.
	 *
	 * This method allows custom content to be added to the table's header, such as actions or additional text.
	 *
	 * Example usage:
	 *
	 *     $table->setHeaderContent('Custom Actions');
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $headerContent The content to be displayed in the table header.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setHeaderContent( string|HtmlSnippet $headerContent ): self {
		$this->headerContent = $headerContent;

		return $this;
	}

	/**
	 * Set the current sort column.
	 *
	 * This method specifies which column is currently being used for sorting the table data.
	 * The column with this ID will be marked as sorted in the table header.
	 *
	 * Example usage:
	 *
	 *     $table->setCurrentSortColumn('title');
	 *
	 * @since 0.1.0
	 * @param string $currentSortColumn The ID of the column used for sorting.
	 *
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setCurrentSortColumn( string $currentSortColumn ): self {
		$this->currentSortColumn = $currentSortColumn;

		return $this;
	}

	/**
	 * Set the current sort direction.
	 *
	 * This method specifies the direction for sorting the table data. Acceptable values are 'asc' for ascending
	 * and 'desc' for descending. The method validates these values to ensure they are correct.
	 *
	 * Example usage:
	 *
	 *     $table->setCurrentSortDirection('asc');
	 *
	 * @since 0.1.0
	 * @param string $currentSortDirection The sort direction ('asc' or 'desc').
	 *
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setCurrentSortDirection( string $currentSortDirection ): self {
		if ( $currentSortDirection === self::SORT_ASCENDING || $currentSortDirection === self::SORT_DESCENDING ) {
			$this->currentSortDirection = $currentSortDirection;
		}

		return $this;
	}
}
