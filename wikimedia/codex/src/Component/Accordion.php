<?php
declare( strict_types = 1 );

/**
 * Accordion.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Accordion` class, responsible for managing
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
use Wikimedia\Codex\Renderer\AccordionRenderer;
use Wikimedia\Codex\Traits\ContentSetter;

/**
 * Accordion
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Accordion extends Component {
	use ContentSetter;

	/**
	 * Allowed styles for the separation.
	 */
	public const ALLOWED_SEPARATIONS = [
		'none',
		'minimal',
		'divider',
		'outline',
	];

	private string $id = '';

	public function __construct(
		AccordionRenderer $renderer,
		private string|HtmlSnippet $title,
		private string|HtmlSnippet $description,
		private string|HtmlSnippet $content,
		private bool $open,
		private string $separation,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the accordion's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the accordion element.
	 * The ID is useful for targeting the accordion with JavaScript, CSS, or accessibility features.
	 *
	 * @since 0.1.0
	 * @return string The ID of the accordion element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the accordion's title.
	 *
	 * This method returns the title displayed in the header of the accordion.
	 * The title is the main clickable element that users interact with to expand or collapse
	 * the accordion's content.
	 *
	 * @since 0.1.0
	 * @return string The title of the accordion.
	 */
	public function getTitle(): string|HtmlSnippet {
		return $this->title;
	}

	/**
	 * Get the accordion's description.
	 *
	 * This method returns the description text that appears below the title in the accordion's header.
	 * The description provides additional context or details about the accordion's content.
	 *
	 * @since 0.1.0
	 * @return string The description of the accordion.
	 */
	public function getDescription(): string|HtmlSnippet {
		return $this->description;
	}

	/**
	 * Get the accordion's content.
	 *
	 * This method returns the content displayed when the accordion is expanded.
	 * The content can include various HTML elements such as text, images, and more.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The content of the accordion, as HTML
	 */
	public function getContent(): string|HtmlSnippet {
		return $this->content;
	}

	/**
	 * Get the style of the separations.
	 *
	 * This method returns the separation style of the accordion, which indicates its visual prominence
	 * (e.g., 'none', 'minimal', 'divider', 'outline').
	 *
	 * @since @next
	 * @return string The style of the separation.
	 */
	public function getSeparation(): string {
		return $this->separation;
	}

	/**
	 * Check if the accordion is open by default.
	 *
	 * This method indicates whether the accordion is set to be expanded by default when the page loads.
	 * If true, the accordion is displayed in an expanded state.
	 *
	 * @since 0.1.0
	 * @return bool True if the accordion is open by default, false otherwise.
	 */
	public function isOpen(): bool {
		return $this->open;
	}

	/**
	 * Retrieve additional HTML attributes for the <details> element.
	 *
	 * This method returns an array of additional HTML attributes that will be applied
	 * to the `<details>` element of the accordion. The attributes are properly escaped
	 * to ensure security and prevent XSS vulnerabilities.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the accordion's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the accordion element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the title for the accordion header.
	 *
	 * This method specifies the title text that appears in the accordion's header section.
	 * The title serves as the main clickable element that users interact with to expand or collapse
	 * the accordion content. The title is rendered inside a `<span>` element with the class
	 * `cdx-accordion__header__title`, which is nested within an `<h3>` header inside the `<summary>` element.
	 *
	 * The title should be concise yet descriptive enough to give users a clear understanding
	 * of the content they will see when the accordion is expanded.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $title The title text to be displayed in the accordion header.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setTitle( string|HtmlSnippet $title ): self {
		$this->title = $title;

		return $this;
	}

	/**
	 * Set the description for the accordion header.
	 *
	 * The description is an optional text that provides additional context or details about the accordion's content.
	 * This text is displayed beneath the title in the header section and is wrapped in a `<span>` element with
	 * the class `cdx-accordion__header__description`. This description is particularly useful when the title alone
	 * does not fully convey the nature of the accordion's content.
	 *
	 * This method is especially helpful for making the accordion more accessible and informative,
	 * allowing users to understand the content before deciding to expand it.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $description The description text to be displayed in the accordion header.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setDescription( string|HtmlSnippet $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set the content of the accordion.
	 *
	 * @param string|HtmlSnippet $content Text or HTML to be displayed inside the accordion.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setContent( string|HtmlSnippet $content ): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * Set whether the accordion should be open by default.
	 *
	 * By default, accordions are rendered in a collapsed state. However, setting this property to `true`
	 * will cause the accordion to be expanded when the page initially loads. This adds the `open` attribute
	 * to the `<details>` element, making the content visible without interaction.
	 *
	 * This feature is useful in scenarios where critical content needs to be immediately visible, without requiring
	 * any action to expand the accordion.
	 *
	 * @since 0.1.0
	 * @param bool $isOpen Indicates whether the accordion should be open by default.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setOpen( bool $isOpen ): self {
		$this->open = $isOpen;

		return $this;
	}

	/**
	 * Set the style for the separations.
	 *
	 * This method sets the visual prominence of the separations, which can be:
	 * - 'none': No visual separation between accordion items (Default).
	 * - 'minimal': A low-emphasis style where only the header/title is highlighted.
	 * - 'divider': A standard horizontal line between items.
	 * - 'outline': Each accordion item is contained within its own border/box.
	 *
	 * The separation style is applied as a CSS class (`cdx-accordion--separation-{separation}`)
	 * to the details element.
	 *
	 * @since @next
	 * @param string $separation The style for the separation.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setSeparation( string $separation ): self {
		if ( !in_array( $separation, self::ALLOWED_SEPARATIONS, true ) ) {
			throw new InvalidArgumentException( "Invalid separation: $separation" );
		}
		$this->separation = $separation;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the `<details>` element.
	 *
	 * This method allows custom attributes to be added to the `<details>` element, such as `id`, `class`, `data-*`,
	 * `role`, or any other valid HTML attributes. These attributes can be used to further customize the accordion
	 * behavior, integrate it with JavaScript, or enhance accessibility.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $accordion->setAttributes([
	 *         'id' => 'some-id',
	 *         'data-toggle' => 'collapse'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
