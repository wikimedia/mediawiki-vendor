<?php
declare( strict_types = 1 );

/**
 * TableRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `TableRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
 * component object is rendered according to Codex design system standards.
 *
 * @category Renderer
 * @package  Codex\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Renderer;

use InvalidArgumentException;
use UnexpectedValueException;
use Wikimedia\Codex\Component\HtmlSnippet;
use Wikimedia\Codex\Component\Table;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\ILocalizer;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\ParamValidator\ParamDefinitions;
use Wikimedia\Codex\ParamValidator\ParamValidator;
use Wikimedia\Codex\ParamValidator\ParamValidatorCallbacks;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * TableRenderer is responsible for rendering the HTML markup
 * for a Table component using a Mustache template.
 *
 * This class uses the `TemplateParser` and `Sanitizer` utilities to manage
 * the template rendering process, ensuring that the component object's HTML
 * output adheres to the Codex design system's standards.
 *
 * @category Renderer
 * @package  Codex\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class TableRenderer extends Renderer {

	/**
	 * Constructor to initialize the TableRenderer with necessary dependencies.
	 *
	 * @since 0.1.0
	 * @param Sanitizer $sanitizer The sanitizer instance for cleaning user-provided data and HTML attributes.
	 * @param TemplateParser $templateParser The template parser instance for rendering Mustache templates.
	 * @param ILocalizer $localizer The localizer instance for i18n messages.
	 * @param ParamValidator $paramValidator The parameter validator instance to validate query parameters.
	 * @param ParamValidatorCallbacks $paramValidatorCallbacks The callbacks instance for fetching validated parameters.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		private readonly TemplateParser $templateParser,
		private readonly ILocalizer $localizer,
		private readonly ParamValidator $paramValidator,
		private readonly ParamValidatorCallbacks $paramValidatorCallbacks
	) {
		parent::__construct( $sanitizer );
	}

	/**
	 * Renders the HTML for a table component.
	 *
	 * Uses the provided Table component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The Table object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof Table ) {
			throw new InvalidArgumentException( "Expected instance of Table, got " . get_class( $component ) );
		}

		$pager = $component->getPager();
		$tableData = [
			'id' => $this->sanitizer->sanitizeText( $component->getId() ),
			'showVerticalBorders' => $component->getShowVerticalBorders(),
			'useRowHeaders' => $component->getUseRowHeaders(),
			'paginationPosition' => $component->getPaginationPosition(),
			'totalRows' => $component->getTotalRows(),
			'caption' => $component->getCaption(),
			'columns' => $this->prepareColumns( $component ),
			'rows' => $this->prepareRows( $component ),
			'hideCaption' => $component->getHideCaption(),
			'headerContent-html' => $this->sanitizer->sanitizeText( $component->getHeaderContent() ?? '' ),
			'hasData' => (bool)count( $component->getData() ),
			'noDataMessage' => count( $component->getData() ) === 0 ?
				$this->localizer->msg( 'cdx-table-no-data-message' ) : '',
			'pager' => $pager ? $pager->getHtml() : '',
			'extraClasses' => $this->getExtraClasses( $component->getAttributes() ),
			'attributes' => $this->getOtherAttributes( $component->getAttributes() ),
			'footer-html' => $this->sanitizer->sanitizeText( $component->getFooter() ?? '' ),
		];
		return $this->templateParser->processTemplate( 'table', $tableData );
	}

	/**
	 * Prepares the column data for rendering in the Mustache template.
	 *
	 * This method takes the columns defined in the Table component and processes them into an array
	 * format suitable for rendering in the table. It handles sorting options, alignment, and the correct
	 * icon for the sorting direction.
	 *
	 * @since 0.1.0
	 * @param Table $table The Table object containing column definitions.
	 * @return array The processed columns ready for rendering.
	 */
	private function prepareColumns( Table $table ): array {
		$columns = [];
		foreach ( $table->getColumns() as $column ) {
			$isCurrentSortColumn = $table->getCurrentSortColumn() === $column['id'];
			$columns[] = [
				'id' => $column['id'],
				'label-html' => $this->sanitizer->sanitizeText( $column['label'] ),
				'align' => $column['align'] ?? '',
				'sortable' => !empty( $column['sortable'] ),
				'isCurrentSort' => $isCurrentSortColumn,
				'sortUrl' => $this->buildSortUrl( $table, $column['id'] ),
				'sortIconClass' => $this->getSortIconClass( $table, $isCurrentSortColumn ),
			];
		}

		return $columns;
	}

	/**
	 * Prepares the row data for rendering in the Mustache template.
	 *
	 * This method processes the data provided in the Table component and matches it with the defined columns.
	 * Each row is prepared as an array of columns with their respective cell data and alignment settings.
	 *
	 * @since 0.1.0
	 * @param Table $table The Table object containing row data.
	 * @return array The processed rows ready for rendering.
	 */
	private function prepareRows( Table $table ): array {
		$rows = [];
		foreach ( $table->getData() as $row ) {
			$rowData = [];
			foreach ( $table->getColumns() as $column ) {
				$id = $row[$column['id']] ?? '';
				if ( !$id instanceof HtmlSnippet ) {
					$id = (string)$id;
				}
				$cellData = $this->sanitizer->sanitizeText( $id );
				$align = $column['align'] ?? '';
				$rowData[] = [
					'cellData-html' => $cellData,
					'align' => $align,
				];
			}
			$rows[] = [ 'columns' => $rowData ];
		}

		return $rows;
	}

	/**
	 * Determines the appropriate CSS class for the sort icon based on the current sort state.
	 *
	 * If the column is the currently sorted column, it returns the correct ascending or descending sort icon class.
	 * Otherwise, it returns the unsorted icon class.
	 *
	 * @since 0.1.0
	 * @param Table $table The Table object.
	 * @param bool $isCurrentSortColumn Whether the column is currently sorted.
	 * @return string The CSS class for the sort icon.
	 */
	private function getSortIconClass( Table $table, bool $isCurrentSortColumn ): string {
		if ( $isCurrentSortColumn ) {
			return $table->getCurrentSortDirection() === Table::SORT_ASCENDING ? 'cdx-table__table__sort-icon--asc'
				: 'cdx-table__table__sort-icon--desc';
		}

		return 'cdx-table__table__sort-icon--unsorted';
	}

	/**
	 * Builds the URL for sorting the table by a specific column.
	 *
	 * This method constructs the sort URL by adjusting the query parameters to reflect the new sort column
	 * and direction (ascending or descending).
	 *
	 * @since 0.1.0
	 * @param Table $table The Table object.
	 * @param string $columnId The ID of the column to sort by.
	 * @return string The generated URL for sorting by the specified column.
	 */
	private function buildSortUrl( Table $table, string $columnId ): string {
		$definitions = ParamDefinitions::getDefinitionsForContext( 'table' );

		foreach ( $definitions as $param => $rules ) {
			try {
				$this->paramValidator->validateValue(
					$param,
					$this->paramValidatorCallbacks->getValue(
						$param,
						$rules[ParamValidator::PARAM_DEFAULT],
						[]
					),
					$rules
				);
			} catch ( UnexpectedValueException $e ) {
				throw new InvalidArgumentException( "Invalid value for parameter '$param': " . $e->getMessage() );
			}
		}

		$isAscending = $table->getCurrentSortDirection() === Table::SORT_ASCENDING;
		return '?' . http_build_query( [
			// Start with all current URL parameters to preserve them
			...$this->paramValidatorCallbacks->getAllParams(),
			// Override only the sort-specific parameters
			'offset' => $this->paramValidatorCallbacks->getValue( 'offset', '', [] ),
			'limit' => $this->paramValidatorCallbacks->getValue( 'limit', 5, [] ),
			'sort' => $columnId,
			'asc' => $isAscending ? '' : 1,
			'desc' => $isAscending ? 1 : '',
		] );
	}
}
