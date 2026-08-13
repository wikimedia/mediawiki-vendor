<?php
declare( strict_types = 1 );

/**
 * Codex.php
 *
 * This class provides factory methods to create instances of various builders,
 * including Accordion, Button, Card, Checkbox, and others. These builders facilitate
 * the creation of standardized UI components adhering to the Codex design principles.
 *
 * Each builder follows the builder pattern, allowing for easy and fluent creation
 * and customization of components used across Wikimedia projects.
 *
 * @category Utility
 * @package  Codex\Utility
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Utility;

use Krinkle\Intuition\Intuition;
use MediaWiki\Context\RequestContext;
use Wikimedia\Codex\Component\Accordion;
use Wikimedia\Codex\Component\Button;
use Wikimedia\Codex\Component\Card;
use Wikimedia\Codex\Component\Checkbox;
use Wikimedia\Codex\Component\Field;
use Wikimedia\Codex\Component\HtmlSnippet;
use Wikimedia\Codex\Component\InfoChip;
use Wikimedia\Codex\Component\Label;
use Wikimedia\Codex\Component\Message;
use Wikimedia\Codex\Component\Pager;
use Wikimedia\Codex\Component\ProgressBar;
use Wikimedia\Codex\Component\Radio;
use Wikimedia\Codex\Component\Select;
use Wikimedia\Codex\Component\Tab;
use Wikimedia\Codex\Component\Table;
use Wikimedia\Codex\Component\Tabs;
use Wikimedia\Codex\Component\TextArea;
use Wikimedia\Codex\Component\TextInput;
use Wikimedia\Codex\Component\Thumbnail;
use Wikimedia\Codex\Component\ToggleSwitch;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\ILocalizer;
use Wikimedia\Codex\Infrastructure\CodexServices;
use Wikimedia\Codex\Localization\IntuitionLocalization;
use Wikimedia\Codex\Localization\MediaWikiLocalization;
use Wikimedia\Codex\Renderer\AccordionRenderer;
use Wikimedia\Codex\Renderer\ButtonRenderer;
use Wikimedia\Codex\Renderer\CardRenderer;
use Wikimedia\Codex\Renderer\CheckboxRenderer;
use Wikimedia\Codex\Renderer\FieldRenderer;
use Wikimedia\Codex\Renderer\InfoChipRenderer;
use Wikimedia\Codex\Renderer\LabelRenderer;
use Wikimedia\Codex\Renderer\MessageRenderer;
use Wikimedia\Codex\Renderer\PagerRenderer;
use Wikimedia\Codex\Renderer\ProgressBarRenderer;
use Wikimedia\Codex\Renderer\RadioRenderer;
use Wikimedia\Codex\Renderer\SelectRenderer;
use Wikimedia\Codex\Renderer\TableRenderer;
use Wikimedia\Codex\Renderer\TabsRenderer;
use Wikimedia\Codex\Renderer\TextAreaRenderer;
use Wikimedia\Codex\Renderer\TextInputRenderer;
use Wikimedia\Codex\Renderer\ThumbnailRenderer;
use Wikimedia\Codex\Renderer\ToggleSwitchRenderer;

/**
 * Codex UI
 *
 * This class provides methods for creating instances of various builders, each
 * corresponding to a UI component in the Codex design system. These builders allow
 * the creation and customization of Codex components.
 *
 * @category Utility
 * @package  Codex\Utility
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Codex {

	/**
	 * The CodexServices instance that manages services.
	 */
	private CodexServices $services;

	private ILocalizer $localizer;

	/**
	 * Create a new Codex instance. This object can be used to create and render Codex components.
	 *
	 * @param ILocalizer|null $localizer Localizer object for i18n.
	 *   NOTE: Omitting this parameter is deprecated and will not be supported in version 1.0.
	 */
	public function __construct( ?ILocalizer $localizer = null ) {
		$this->services = CodexServices::getInstance();
		if ( $localizer === null ) {
			trigger_error(
				'Not passing a localizer to the Codex constructor is deprecated and will not be supported ' .
				'in version 1.0.',
				E_USER_DEPRECATED
			);
		}
		$this->localizer = $localizer ?? $this->getFallbackLocalizer();
	}

	/**
	 * Derive a localizer from the global state, if one wasn't passed to the constructor.
	 */
	private function getFallbackLocalizer(): ILocalizer {
		if ( defined( 'MW_INSTALL_PATH' ) ) {
			$messageLocalizer = RequestContext::getMain();
			return new MediaWikiLocalization( $messageLocalizer );
		} else {
			$intuition = new Intuition( 'codex' );
			$intuition->registerDomain( 'codex', __DIR__ . '/../../i18n' );
			return new IntuitionLocalization( $intuition );
		}
	}

	/**
	 * Build an Accordion component.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $title The accordion's header title.
	 * @param string|HtmlSnippet $description Additional text under the title.
	 * @param string|HtmlSnippet $content The content shown when the accordion is expanded.
	 * @param bool $open Determines if the accordion is expanded by default.
	 * @param string $separation The visual prominence of the separation ('none', 'minimal', 'divider', 'outline').
	 * @param array $attributes Additional HTML attributes for the <details> element.
	 * @return Accordion The Accordion component instance.
	 */
	public function accordion(
		string|HtmlSnippet $title = '',
		string|HtmlSnippet $description = '',
		string|HtmlSnippet $content = '',
		bool $open = false,
		string $separation = 'none',
		array $attributes = []
	): Accordion {
		return new Accordion(
			new AccordionRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$title,
			$description,
			$content,
			$open,
			$separation,
			$attributes
		);
	}

	/**
	 * Build a Button component.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $label The text label displayed on the button.
	 * @param string $action The visual action style of the button ('default', 'progressive' or 'destructive')
	 * @param string $size The size of the button ('medium' or 'large').
	 * @param string $type The type of the button ('button', 'submit' or 'reset').
	 * @param string $weight The visual prominence of the button ('normal', 'primary' or 'quiet')
	 * @param string|null $iconClass The CSS class for an icon, if any.
	 * @param bool $iconOnly Indicates if the button is icon-only (no text).
	 * @param bool $disabled Indicates if the button is disabled.
	 * @param array $attributes Additional HTML attributes for the button element.
	 * @param string|null $href The URL the button should redirect to.
	 * @return Button The Button component instance.
	 */
	public function button(
		string|HtmlSnippet $label = '',
		string $action = 'default',
		string $size = 'medium',
		string $type = 'button',
		string $weight = 'normal',
		?string $iconClass = null,
		bool $iconOnly = false,
		bool $disabled = false,
		array $attributes = [],
		?string $href = null,
	): Button {
		return new Button(
			new ButtonRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$label,
			$action,
			$size,
			$type,
			$weight,
			$iconClass,
			$iconOnly,
			$disabled,
			$attributes,
			$href
		);
	}

	/**
	 * Builds a Card component
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $title The title text displayed on the card.
	 * @param string|HtmlSnippet $description The description text displayed on the card.
	 * @param string|HtmlSnippet $supportingText The supporting text displayed on the card.
	 * @param string $url The URL the card links to, if clickable.
	 * @param ?string $iconClass The CSS class for an optional icon in the card.
	 * @param ?Thumbnail $thumbnail The Thumbnail object representing the card's thumbnail.
	 * @param array $attributes Additional HTML attributes for the card element.
	 * @return Card The Card component instance.
	 */
	public function card(
		string|HtmlSnippet $title = '',
		string|HtmlSnippet $description = '',
		string|HtmlSnippet $supportingText = '',
		string $url = '',
		?string $iconClass = null,
		?Thumbnail $thumbnail = null,
		array $attributes = [],
	): Card {
		return new Card(
			new CardRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$title,
			$description,
			$supportingText,
			$url,
			$iconClass,
			$thumbnail,
			$attributes
		);
	}

	/**
	 * Build a Checkbox component
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID for the checkbox input.
	 * @param string $name The name attribute for the checkbox input.
	 * @param ?Label $label The Label object associated with the checkbox.
	 * @param string $value The value associated with the checkbox input.
	 * @param bool $checked Indicates if the checkbox is selected by default.
	 * @param bool $disabled Indicates if the checkbox is disabled.
	 * @param bool $inline Indicates if the checkbox should be displayed inline.
	 * @param array $inputAttributes Additional HTML attributes for the input element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @return Checkbox The Checkbox component instance.
	 */
	public function checkbox(
		string $inputId = '',
		string $name = '',
		?Label $label = null,
		string $value = '',
		bool $checked = false,
		bool $disabled = false,
		bool $inline = false,
		array $inputAttributes = [],
		array $wrapperAttributes = []
	): Checkbox {
		return new Checkbox(
			new CheckboxRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->localizer
			),
			$inputId,
			$name,
			$label,
			$value,
			$checked,
			$disabled,
			$inline,
			$inputAttributes,
			$wrapperAttributes
		);
	}

	/**
	 * Build a Field component
	 *
	 * @since 0.1.0
	 * @param ?Label $label The label for the field or fieldset.
	 * @param bool $isFieldset Indicates if fields are wrapped in a fieldset.
	 * @param array<Component|HtmlSnippet|string> $fields An array of fields.
	 *   Strings are interpreted as raw HTML. Passing in strings is deprecated.
	 * @param array $attributes Additional HTML attributes for the wrapper div or fieldset.
	 * @return Field The Field component instance.
	 */
	public function field(
		?Label $label = null,
		bool $isFieldset = false,
		array $fields = [],
		array $attributes = []
	): Field {
		return new Field(
			new FieldRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->localizer,
				$this
			),
			$label,
			$isFieldset,
			$fields,
			$attributes
		);
	}

	/**
	 * Returns an HTMLSnippet that encapsulates the given HTML string
	 *
	 * @since 0.1.0
	 * @param string $html HTML string
	 * @return HtmlSnippet
	 */
	public function htmlSnippet( string $html = '' ): HtmlSnippet {
		return new HtmlSnippet( $html );
	}

	/**
	 * Build an InfoChip component
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $text The text displayed inside the InfoChip.
	 * @param string $status The status type of the InfoChip, which determines chip's visual style.
	 *   This can be 'notice', 'warning', 'error', or 'success'.
	 * @param ?string $icon The CSS class for a custom icon, if any.
	 *   Only used if $status is set to 'notice', ignored otherwise.
	 * @param array $attributes Additional HTML attributes for the InfoChip element.
	 * @return InfoChip The InfoChip component instance.
	 */
	public function infoChip(
		string|HtmlSnippet $text = '',
		string $status = 'notice',
		?string $icon = null,
		array $attributes = []
	): InfoChip {
		return new InfoChip(
			new InfoChipRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$text,
			$status,
			$icon,
			$attributes
		);
	}

	/**
	 * Build a Label component
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $labelText The text displayed inside the label.
	 * @param string $inputId The ID of the input/control this label is associated with.
	 * @param bool $optional Indicates whether the associated input field is optional.
	 * @param bool $visuallyHidden Indicates whether the label should be visually hidden.
	 * @param bool $isLegend Indicates if the label should be rendered as a `<legend>` element.
	 * @param string|HtmlSnippet $description The description text for the label.
	 * @param string|null $descriptionId The ID of the description element.
	 * @param bool $disabled Indicates whether the label is for a disabled input.
	 * @param string|null $iconClass The CSS class for an icon before the label text.
	 * @param array $attributes Additional HTML attributes for the label element.
	 * @return Label The Label component instance.
	 */
	public function label(
		string|HtmlSnippet $labelText = '',
		string $inputId = '',
		bool $optional = false,
		bool $visuallyHidden = false,
		bool $isLegend = false,
		string|HtmlSnippet $description = '',
		?string $descriptionId = null,
		bool $disabled = false,
		?string $iconClass = null,
		array $attributes = []
	): Label {
		return new Label(
			new LabelRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->localizer
			),
			$labelText,
			$inputId,
			$optional,
			$visuallyHidden,
			$isLegend,
			$description,
			$descriptionId,
			$disabled,
			$iconClass,
			$attributes
		);
	}

	/**
	 * Build a Message component.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $content The content displayed inside the message box.
	 * @param string $type The type of the message.
	 *   This can be 'notice', 'warning', 'error', or 'success'.
	 * @param bool $inline Whether the message should be displayed inline.
	 * @param string|HtmlSnippet $heading The heading displayed at the top of the message content.
	 * @param string $iconClass The CSS class name for the icon.
	 * @param array $attributes Additional HTML attributes for the message box.
	 * @return Message The Message component instance.
	 */
	public function message(
		string|HtmlSnippet $content = '',
		string $type = 'notice',
		bool $inline = false,
		string|HtmlSnippet $heading = '',
		string $iconClass = '',
		array $attributes = []
	): Message {
		return new Message(
			new MessageRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$content,
			$type,
			$inline,
			$heading,
			$iconClass,
			$attributes
		);
	}

	/**
	 * Build a Pager component
	 *
	 * @since 0.1.0
	 * @param array $paginationSizeOptions Available pagination size options.
	 * @param int $paginationSizeDefault Default pagination size.
	 * @param int $totalPages Total number of pages in the dataset.
	 * @param int $totalResults Total number of results in the dataset.
	 * @param string $position Position of the pagination controls ('top', 'bottom' or 'both').
	 * @param int $limit Number of results per page.
	 * @param int|null $currentOffset Offset of the current page.
	 * @param int|null $nextOffset Offset for the next page.
	 * @param int|null $prevOffset Offset for the previous page.
	 * @param int|null $firstOffset Offset for the first page.
	 * @param int|null $lastOffset Offset for the last page.
	 * @param int $startOrdinal Start ordinal for the current page.
	 * @param int $endOrdinal End ordinal for the current page.
	 * @return Pager The Pager component instance.
	 */
	public function pager(
		array $paginationSizeOptions = [
			5,
			10,
			25,
			50,
			100,
		],
		int $paginationSizeDefault = 10,
		int $totalPages = 1,
		int $totalResults = 0,
		string $position = 'bottom',
		int $limit = 10,
		?int $currentOffset = null,
		?int $nextOffset = null,
		?int $prevOffset = null,
		?int $firstOffset = null,
		?int $lastOffset = null,
		int $startOrdinal = 1,
		int $endOrdinal = 1,
		array $attributes = [],
	): Pager {
		return new Pager(
			new PagerRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->localizer,
				$this,
				$this->services->getService( 'ParamValidator' ),
				$this->services->getService( 'ParamValidatorCallbacks' )
			),
			$paginationSizeOptions,
			$paginationSizeDefault,
			$totalPages,
			$totalResults,
			$position,
			$limit,
			$currentOffset,
			$nextOffset,
			$prevOffset,
			$firstOffset,
			$lastOffset,
			$startOrdinal,
			$endOrdinal,
			$attributes,
		);
	}

	/**
	 * Resolves and returns the ProgressBar builder.
	 *
	 * @since 0.1.0
	 * @param string $label The ARIA label for the progress bar, important for accessibility.
	 * @param bool $inline Whether the progress bar is a smaller, inline variant.
	 * @param bool $disabled Whether the progress bar is disabled.
	 * @param array $attributes Additional HTML attributes for the outer `<div>` element of the progress bar.
	 * @return ProgressBar The ProgressBar instance.
	 */
	public function progressBar(
		string $label = '',
		bool $inline = false,
		bool $disabled = false,
		array $attributes = []
	): ProgressBar {
		return new ProgressBar(
			new ProgressBarRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$label,
			$inline,
			$disabled,
			$attributes
		);
	}

	/**
	 * Build a Radio component
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID for the radio input.
	 * @param string $name The name attribute for the radio input.
	 * @param ?Label $label The Label for the radio.
	 * @param string $value The value associated with the radio input.
	 * @param bool $checked Indicates if the radio is selected by default.
	 * @param bool $disabled Indicates if the radio is disabled.
	 * @param bool $inline Indicates if the radio should be displayed inline.
	 * @param array $inputAttributes Additional HTML attributes for the input element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @return Radio The Radio component instance.
	 */
	public function radio(
		string $inputId = '',
		string $name = '',
		?Label $label = null,
		string $value = '',
		bool $checked = false,
		bool $disabled = false,
		bool $inline = false,
		array $inputAttributes = [],
		array $wrapperAttributes = []
	): Radio {
		return new Radio(
			new RadioRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->localizer
			),
			$inputId,
			$name,
			$label,
			$value,
			$checked,
			$disabled,
			$inline,
			$inputAttributes,
			$wrapperAttributes
		);
	}

	/**
	 * Build a Select component
	 *
	 * @since 0.1.0
	 * @param array $options An array of options for the select element.
	 * @param array $optGroups An array of optGroups for grouping options.
	 * @param string|null $selectedOption The value of the selected option, if any.
	 * @param bool $disabled Indicates whether the select element is disabled.
	 * @param array $attributes Additional HTML attributes for the select element.
	 * @return Select The Select component instance.
	 */
	public function select(
		array $options = [],
		array $optGroups = [],
		?string $selectedOption = null,
		bool $disabled = false,
		array $attributes = []
	): Select {
		return new Select(
			new SelectRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$options,
			$optGroups,
			$selectedOption,
			$disabled,
			$attributes
		);
	}

	/**
	 * Build a Tab component.
	 *
	 * @since 0.1.0
	 * @param string $name The unique name of the tab.
	 * @param string $label The label of the tab.
	 * @param string|HtmlSnippet $content The content of the tab.
	 * @param bool $selected Whether the tab is selected by default.
	 * @param bool $disabled Whether the tab is disabled.
	 * @return Tab The Tab instance.
	 */
	public function tab(
		string $name = '',
		string $label = '',
		string|HtmlSnippet $content = '',
		bool $selected = false,
		bool $disabled = false
	): Tab {
		return new Tab(
			$name,
			$label,
			$content,
			$selected,
			$disabled
		);
	}

	/**
	 * Build a Table component
	 *
	 * @since 0.1.0
	 * @param string $caption The caption for the table. Important for accessibility.
	 * @param bool $hideCaption Whether the caption is hidden.
	 * @param array $columns Array of columns.
	 * @param array $data Array of row data.
	 * @param bool $useRowHeaders Whether to use row headers.
	 * @param string|HtmlSnippet|null $headerContent The header content.
	 * @param array $sort Array of sorting configurations.
	 * @param ?string $currentSortColumn The current sorted column.
	 * @param string $currentSortDirection The current sort direction.
	 * @param bool $showVerticalBorders Whether to show vertical borders.
	 * @param bool $paginate Whether pagination is enabled.
	 * @param int $totalRows The total number of rows.
	 * @param string $paginationPosition The pagination position ('top', 'bottom' or 'both')
	 * @param ?Pager $pager The pager for handling pagination.
	 * @param string|HtmlSnippet|null $footer The footer content.
	 * @param array $attributes Additional HTML attributes.
	 * @return Table The Table component instance.
	 */
	public function table(
		string $caption = '',
		bool $hideCaption = false,
		array $columns = [],
		array $data = [],
		bool $useRowHeaders = false,
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes
		string|HtmlSnippet|null $headerContent = null,
		array $sort = [],
		?string $currentSortColumn = null,
		string $currentSortDirection = Table::SORT_ASCENDING,
		bool $showVerticalBorders = false,
		bool $paginate = false,
		int $totalRows = 0,
		string $paginationPosition = 'bottom',
		?Pager $pager = null,
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes
		string|HtmlSnippet|null $footer = null,
		array $attributes = []
	): Table {
		return new Table(
			new TableRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->localizer,
				$this->services->getService( 'ParamValidator' ),
				$this->services->getService( 'ParamValidatorCallbacks' )
			),
			$caption,
			$hideCaption,
			$columns,
			$data,
			$useRowHeaders,
			$headerContent,
			$sort,
			$currentSortColumn,
			$currentSortDirection,
			$showVerticalBorders,
			$paginate,
			$totalRows,
			$paginationPosition,
			$pager,
			$footer,
			$attributes
		);
	}

	/**
	 * Build a Tabs component.
	 *
	 * @since 0.1.0
	 * @param Tab[] $tabs An array of Tab component objects.
	 * @param array $attributes Additional HTML attributes for the tabs component.
	 * @return Tabs The Tabs component instance.
	 */
	public function tabs(
		array $tabs = [],
		array $attributes = []
	): Tabs {
		return new Tabs(
			new TabsRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->services->getService( 'ParamValidator' ),
				$this->services->getService( 'ParamValidatorCallbacks' )
			),
			$tabs,
			$attributes
		);
	}

	/**
	 * Build a TextArea component
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the textarea.
	 * @param string $value The initial contents of the textarea.
	 * @param string $inputId The ID attribute for the textarea element.
	 * @param array $inputAttributes Additional HTML attributes for the textarea element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @param bool $disabled Indicates whether the textarea is disabled.
	 * @param bool $readonly Indicates whether the textarea is read-only.
	 *   If true, the content cannot be modified but can be selected.
	 * @param bool $hasStartIcon Indicates if a start icon should be displayed.
	 * @param bool $hasEndIcon Indicates if an end icon should be displayed.
	 * @param string $startIconClass CSS class for the start icon.
	 * @param string $endIconClass CSS class for the end icon.
	 * @param string $placeholder Placeholder text for the textarea.
	 * @param string $status Validation status ('default', 'error', 'warning' or 'success')
	 * @return TextArea The TextArea component instance.
	 */
	public function textArea(
		string $name = '',
		string $value = '',
		string $inputId = '',
		array $inputAttributes = [],
		array $wrapperAttributes = [],
		bool $disabled = false,
		bool $readonly = false,
		bool $hasStartIcon = false,
		bool $hasEndIcon = false,
		string $startIconClass = '',
		string $endIconClass = '',
		string $placeholder = '',
		string $status = 'default'
	): TextArea {
		return new TextArea(
			new TextAreaRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$name,
			$value,
			$inputId,
			$inputAttributes,
			$wrapperAttributes,
			$disabled,
			$readonly,
			$hasStartIcon,
			$hasEndIcon,
			$startIconClass,
			$endIconClass,
			$placeholder,
			$status
		);
	}

	/**
	 * Build a TextInput component
	 *
	 * @since 0.1.0
	 * @param string $type The type of the input field (e.g., 'text', 'email'; see TEXT_INPUT_TYPES).
	 * @param string $name Name attribute of the input.
	 * @param string $value Default value of the input.
	 * @param string $inputId ID attribute for the input.
	 * @param string $placeholder Placeholder text for the input.
	 * @param bool $disabled Indicates if the input is disabled.
	 * @param string $status Validation status ('default', 'error', 'warning' or 'success')
	 * @param array $inputAttributes Additional HTML attributes for the input element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @param bool $hasStartIcon Indicates if the input has a start icon.
	 * @param bool $hasEndIcon Indicates if the input has an end icon.
	 * @param ?string $startIconClass CSS class for the start icon.
	 * @param ?string $endIconClass CSS class for the end icon.
	 * @return TextInput The TextInput component instance.
	 */
	public function textInput(
		string $type = 'text',
		string $name = '',
		string $value = '',
		string $inputId = '',
		string $placeholder = '',
		bool $disabled = false,
		string $status = 'default',
		array $inputAttributes = [],
		array $wrapperAttributes = [],
		bool $hasStartIcon = false,
		bool $hasEndIcon = false,
		?string $startIconClass = null,
		?string $endIconClass = null,
	): TextInput {
		return new TextInput(
			new TextInputRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$type,
			$name,
			$value,
			$inputId,
			$placeholder,
			$disabled,
			$status,
			$inputAttributes,
			$wrapperAttributes,
			$hasStartIcon,
			$hasEndIcon,
			$startIconClass,
			$endIconClass
		);
	}

	/**
	 * Build a Thumbnail component
	 *
	 * @since 0.1.0
	 * @param ?string $backgroundImage The background image URL.
	 * @param ?string $placeholderClass The CSS class for the placeholder icon.
	 * @param array $attributes Additional HTML attributes for the thumbnail.
	 * @return Thumbnail The Thumbnail component instance.
	 */
	public function thumbnail(
		?string $backgroundImage = null,
		?string $placeholderClass = null,
		array $attributes = []
	): Thumbnail {
		return new Thumbnail(
			new ThumbnailRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' )
			),
			$backgroundImage,
			$placeholderClass,
			$attributes
		);
	}

	/**
	 * Build a ToggleSwitch component
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID for the toggle switch input.
	 * @param string $name The name attribute for the toggle switch input.
	 * @param ?Label $label The label object for the toggle switch.
	 * @param string $value The value associated with the toggle switch input.
	 * @param bool $checked Whether the toggle switch is checked by default.
	 * @param bool $disabled Whether the toggle switch is disabled.
	 * @param array $inputAttributes Additional HTML attributes for the input element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @return ToggleSwitch The ToggleSwitch component instance.
	 */
	public function toggleSwitch(
		string $inputId = '',
		string $name = '',
		?Label $label = null,
		string $value = '',
		bool $checked = false,
		bool $disabled = false,
		array $inputAttributes = [],
		array $wrapperAttributes = []
	): ToggleSwitch {
		return new ToggleSwitch(
			new ToggleSwitchRenderer(
				$this->services->getService( 'Sanitizer' ),
				$this->services->getService( 'TemplateParser' ),
				$this->localizer
			),
			$inputId,
			$name,
			$label,
			$value,
			$checked,
			$disabled,
			$inputAttributes,
			$wrapperAttributes
		);
	}
}
