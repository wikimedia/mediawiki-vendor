<?php
declare( strict_types = 1 );

/**
 * PagerRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `PagerRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\Pager;
use Wikimedia\Codex\Component\Select;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\ILocalizer;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\ParamValidator\ParamDefinitions;
use Wikimedia\Codex\ParamValidator\ParamValidator;
use Wikimedia\Codex\ParamValidator\ParamValidatorCallbacks;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * PagerRenderer is responsible for rendering the HTML markup
 * for a Pager component using a Mustache template.
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
class PagerRenderer extends Renderer {

	/**
	 * The template parser instance.
	 */
	private TemplateParser $templateParser;

	/**
	 * The localization instance implementing ILocalizer.
	 */
	private ILocalizer $localizer;

	/**
	 * The Codex instance for utility methods.
	 */
	private Codex $codex;

	/**
	 * The param validator.
	 */
	protected ParamValidator $paramValidator;

	/**
	 * The param validator callbacks.
	 */
	protected ParamValidatorCallbacks $paramValidatorCallbacks;

	/**
	 * Array of icon classes for the pager buttons.
	 */
	private const ICON_CLASSES = [
		"first" => "cdx-table-pager__icon--first",
		"previous" => "cdx-table-pager__icon--previous",
		"next" => "cdx-table-pager__icon--next",
		"last" => "cdx-table-pager__icon--last",
	];

	/**
	 * Action for the first page.
	 */
	private const ACTION_FIRST = 'first';

	/**
	 * Action for the previous page.
	 */
	private const ACTION_PREVIOUS = 'previous';

	/**
	 * Action for the next page.
	 */
	private const ACTION_NEXT = 'next';

	/**
	 * Action for the last page.
	 */
	private const ACTION_LAST = 'last';

	/**
	 * Constructor to initialize the PagerRenderer with necessary dependencies.
	 *
	 * @since 0.1.0
	 * @param Sanitizer $sanitizer The sanitizer instance for cleaning user-provided data and attributes.
	 * @param TemplateParser $templateParser The template parser instance for rendering Mustache templates.
	 * @param ILocalizer $localizer The localizer instance for supporting translations and localization.
	 * @param Codex $codex The Codex instance for creating instances of other components
	 * @param ParamValidator $paramValidator The parameter validator instance for validating query parameters.
	 * @param ParamValidatorCallbacks $paramValidatorCallbacks The callback instance for accessing validated
	 *                                                         parameter values.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		TemplateParser $templateParser,
		ILocalizer $localizer,
		Codex $codex,
		ParamValidator $paramValidator,
		ParamValidatorCallbacks $paramValidatorCallbacks
	) {
		parent::__construct( $sanitizer );
		$this->templateParser = $templateParser;
		$this->localizer = $localizer;
		$this->codex = $codex;
		$this->paramValidator = $paramValidator;
		$this->paramValidatorCallbacks = $paramValidatorCallbacks;
	}

	/**
	 * Renders the HTML for a pager component.
	 *
	 * Uses the provided Pager to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The Pager object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof Pager ) {
			throw new InvalidArgumentException( "Expected instance of Pager, got " . get_class( $component ) );
		}

		$isPending = $component->getEndOrdinal() < $component->getStartOrdinal();
		$totalResults = $component->getTotalResults();

		$pagerData = [
			'id' => $component->getId(),
			'position' => $component->getPosition(),
			'status' => $isPending ?
				$this->localizer->msg( 'cdx-table-pagination-status-message-pending' ) :
				$this->localizer->msg(
					$totalResults > 0 ?
						'cdx-table-pagination-status-message-determinate-long' :
						'cdx-table-pagination-status-message-indeterminate-long',
					$component->getStartOrdinal(),
					$component->getEndOrdinal(),
					$totalResults
				),
			'select' => $this->buildSelect( $component )->getHtml(),
			'firstButton' => $this->buildButtonData( $component, self::ACTION_FIRST ),
			'prevButton' => $this->buildButtonData( $component, self::ACTION_PREVIOUS ),
			'nextButton' => $this->buildButtonData( $component, self::ACTION_NEXT ),
			'lastButton' => $this->buildButtonData( $component, self::ACTION_LAST ),
			'hiddenFields' => $this->buildHiddenFields(),
			'extraClasses' => $this->getExtraClasses( $component->getAttributes() ),
			'attributes' => $this->getOtherAttributes( $component->getAttributes() ),
		];

		return $this->templateParser->processTemplate( 'pager', $pagerData );
	}

	/**
	 * Build the select dropdown data to be passed to the Mustache template.
	 *
	 * @since 0.1.0
	 * @param Pager $pager
	 * @return Select The select component
	 */
	protected function buildSelect( Pager $pager ): Select {
		$sizeOptions = $pager->getPaginationSizeOptions();
		$currentLimit = $pager->getLimit();

		$options = [];

		foreach ( $sizeOptions as $size ) {
			$options[] = [
				'value' => $size,
				'text' => $this->localizer->msg(
					'cdx-table-pager-items-per-page-current', $size
				),
				'selected' => ( $size == $currentLimit ),
			];
		}

		return $this->codex->select()
			->setOptions( $options )
			->setSelectedOption( (string)$currentLimit )
			->setAttributes( [
				'name' => 'limit',
				'onchange' => 'this.form.submit();',
			] );
	}

	/**
	 * Build an individual pagination button.
	 *
	 * Generates the data array for a single pagination button based on the action.
	 *
	 * @since 0.1.0
	 * @param Pager $pager The Pager object.
	 * @param string $action The action for the button (one of the ACTION_* constants).
	 * @return array The data array for the pagination button.
	 */
	protected function buildButtonData( Pager $pager, string $action ): array {
		$iconClass = self::ICON_CLASSES[$action] ?? '';
		switch ( $action ) {
			case self::ACTION_FIRST:
				$disabled = $pager->isFirstDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-first-page';
				$offset = $pager->getFirstOffset();
				break;
			case self::ACTION_PREVIOUS:
				$disabled = $pager->isPrevDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-prev-page';
				$offset = $pager->getPrevOffset();
				break;
			case self::ACTION_NEXT:
				$disabled = $pager->isNextDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-next-page';
				$offset = $pager->getNextOffset();
				break;
			case self::ACTION_LAST:
				$disabled = $pager->isLastDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-last-page';
				$offset = $pager->getLastOffset();
				// FIXME this should maybe set dir=prev as well, but that's not easy with a button
				break;
			default:
				throw new InvalidArgumentException( "Unknown action: $action" );
		}

		return [
			'isDisabled' => $disabled,
			'weight' => 'quiet',
			'iconOnly' => true,
			'iconClass' => $iconClass,
			'type' => 'submit',
			'name' => 'offset',
			'value' => $offset,
			'attributes' => $this->getOtherAttributes( [
				'aria-label' => $this->localizer->msg( $ariaLabelKey ),
			] ),
			'extraClasses' => '',
		];
	}

	/**
	 * Build hidden fields for the pagination form.
	 *
	 * This method generates the hidden input fields needed for the pagination form, including offset, direction,
	 * and other query parameters.
	 *
	 * @since 0.1.0
	 * @return array The generated HTML string for the hidden fields.
	 */
	protected function buildHiddenFields(): array {
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

		$fields = [];
		$keys = [ 'sort', 'desc', 'asc' ];
		foreach ( $keys as $key ) {
			$value = $this->paramValidatorCallbacks->getValue( $key, '', [] );
			if ( $value !== '' ) {
				$fields[] = [
					'key' => $key,
					'value' => $value,
				];
			}
		}

		return $fields;
	}
}
