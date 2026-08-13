<?php
declare( strict_types = 1 );

/**
 * Button.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Button` class, responsible for managing
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
use Wikimedia\Codex\Renderer\ButtonRenderer;

/**
 * Button
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Button extends Component {
	/**
	 * Allowed action styles for the button.
	 */
	public const ALLOWED_ACTIONS = [
		'default',
		'progressive',
		'destructive',
	];

	/**
	 * Allowed sizes for the button.
	 */
	public const ALLOWED_SIZES = [
		'medium',
		'large',
	];

	/**
	 * Allowed button types.
	 */
	public const ALLOWED_TYPES = [
		'button',
		'submit',
		'reset',
	];

	/**
	 * Allowed weight styles for the button.
	 */
	public const ALLOWED_WEIGHTS = [
		'normal',
		'primary',
		'quiet',
	];

	private string $id = '';

	public function __construct(
		ButtonRenderer $renderer,
		private string|HtmlSnippet $label,
		private string $action,
		private string $size,
		private string $type,
		private string $weight,
		private ?string $iconClass,
		private bool $iconOnly,
		private bool $disabled,
		private array $attributes,
		private ?string $href = null,
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the button's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the button element.
	 * The ID is useful for targeting the button with JavaScript, CSS, or accessibility features.
	 *
	 * @since 0.1.0
	 * @return string The ID of the button element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the label displayed on the button.
	 *
	 * This method returns the text label displayed on the button. The label provides context
	 * to users about the button's action, ensuring that it is understandable and accessible.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The label of the button.
	 */
	public function getLabel(): string|HtmlSnippet {
		return $this->label;
	}

	/**
	 * Get the action style of the button.
	 *
	 * This method returns the action style of the button, indicating the visual style
	 * that reflects the nature of the action it represents (e.g., 'default', 'progressive', 'destructive').
	 *
	 * @since 0.1.0
	 * @return string The action style of the button.
	 */
	public function getAction(): string {
		return $this->action;
	}

	/**
	 * Get the weight style of the button.
	 *
	 * This method returns the weight style of the button, which indicates its visual prominence
	 * (e.g., 'normal', 'primary', 'quiet').
	 *
	 * @since 0.1.0
	 * @return string The weight style of the button.
	 */
	public function getWeight(): string {
		return $this->weight;
	}

	/**
	 * Get the size of the button.
	 *
	 * This method returns the size of the button, determining whether it is 'medium' or 'large'.
	 *
	 * @since 0.1.0
	 * @return string The size of the button.
	 */
	public function getSize(): string {
		return $this->size;
	}

	/**
	 * Get the type of the button.
	 *
	 * This method returns the type of the button, determining whether it is 'button', 'submit', or 'reset'.
	 *
	 * @since 0.1.0
	 * @return string The type of the button.
	 */
	public function getType(): string {
		return $this->type ?: 'button';
	}

	/**
	 * Get the icon class for the button.
	 *
	 * This method returns the CSS class used for the icon displayed inside the button.
	 * The icon is an additional visual element that can be included in the button to enhance usability.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the icon, or null if no icon is set.
	 */
	public function getIconClass(): ?string {
		return $this->iconClass;
	}

	/**
	 * Check if the button is icon-only.
	 *
	 * This method returns a boolean value indicating whether the button is icon-only (i.e., displays only an icon
	 * without any text). This is useful in scenarios where space is limited.
	 *
	 * @since 0.1.0
	 * @return bool True if the button is icon-only, false otherwise.
	 */
	public function isIconOnly(): bool {
		return $this->iconOnly;
	}

	/**
	 * Check if the button is disabled.
	 *
	 * This method returns a boolean value indicating whether the button is disabled.
	 *
	 * @since 0.1.0
	 * @return bool True if the button is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Retrieve additional HTML attributes for the button element.
	 *
	 * This method returns an associative array of additional HTML attributes that will be applied
	 * to the <button> element. These attributes can be used to enhance customization, improve accessibility,
	 * and facilitate JavaScript integration.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the button's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the button element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the label for the button.
	 *
	 * This method defines the text that will be displayed on the button. The label is crucial for providing
	 * users with context about the button's action. In cases where the button is not icon-only, the label
	 * will be wrapped in a `<span>` element within the button.
	 *
	 * It's important to use concise and descriptive text for the label to ensure usability.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $label The text label displayed on the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setLabel( string|HtmlSnippet $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set the action style for the button.
	 *
	 * This method determines the visual style of the button, which reflects the nature of the action
	 * it represents. The action can be one of the following:
	 * - 'default': A standard action button with no special emphasis.
	 * - 'progressive': Indicates a positive or confirmatory action, often styled with a green or blue background.
	 * - 'destructive': Used for actions that have a significant or irreversible impact, typically styled in red.
	 *
	 * The action style is applied as a CSS class (`cdx-button--action-{action}`) to the button element.
	 *
	 * @since 0.1.0
	 * @param string $action The action style for the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setAction( string $action ): self {
		if ( !in_array( $action, self::ALLOWED_ACTIONS, true ) ) {
			throw new InvalidArgumentException( "Invalid action: $action" );
		}
		$this->action = $action;

		return $this;
	}

	/**
	 * Set the weight style for the button.
	 *
	 * This method sets the visual prominence of the button, which can be:
	 * - 'normal': A standard button with default emphasis.
	 * - 'primary': A high-importance button that stands out, often used for primary actions.
	 * - 'quiet': A subtle, low-emphasis button, typically used for secondary or tertiary actions.
	 *
	 * The weight style is applied as a CSS class (`cdx-button--weight-{weight}`) to the button element.
	 *
	 * @since 0.1.0
	 * @param string $weight The weight style for the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setWeight( string $weight ): self {
		if ( !in_array( $weight, self::ALLOWED_WEIGHTS, true ) ) {
			throw new InvalidArgumentException( "Invalid weight: $weight" );
		}
		$this->weight = $weight;

		return $this;
	}

	/**
	 * Set the size of the button.
	 *
	 * This method defines the size of the button, which can be either:
	 * - 'medium': The default size, suitable for most use cases.
	 * - 'large': A larger button, often used to improve accessibility or to emphasize an action.
	 *
	 * The size is applied as a CSS class (`cdx-button--size-{size}`) to the button element.
	 *
	 * @since 0.1.0
	 * @param string $size The size of the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setSize( string $size ): self {
		if ( !in_array( $size, self::ALLOWED_SIZES, true ) ) {
			throw new InvalidArgumentException( "Invalid size: $size" );
		}
		$this->size = $size;

		return $this;
	}

	/**
	 * Set the type of the button.
	 *
	 * This method sets the button's type attribute, which can be one of the following:
	 * - 'button': A standard clickable button.
	 * - 'submit': A button used to submit a form.
	 * - 'reset': A button used to reset form fields to their initial values.
	 *
	 * The type attribute is applied directly to the `<button>` element.
	 *
	 * @since 0.1.0
	 * @param string $type The type for the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setType( string $type ): self {
		if ( !in_array( $type, self::ALLOWED_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid button type: $type" );
		}
		$this->type = $type;

		return $this;
	}

	/**
	 * Set the icon class for the button.
	 *
	 * This method specifies a CSS class for an icon to be displayed inside the button. The icon is rendered
	 * within a `<span>` element with the class `cdx-button__icon`, and should be defined using a suitable
	 * icon font or SVG sprite.
	 *
	 * The icon enhances the button's usability by providing a visual cue regarding the button's action.
	 *
	 * @since 0.1.0
	 * @param string $iconClass The CSS class for the icon.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setIconClass( string $iconClass ): self {
		$this->iconClass = $iconClass;

		return $this;
	}

	/**
	 * Set whether the button should be icon-only.
	 *
	 * This method determines whether the button should display only an icon, without any text.
	 * When set to `true`, the button will only render the icon, making it useful for scenarios where
	 * space is limited, such as in toolbars or mobile interfaces.
	 *
	 * Icon-only buttons should always include an `aria-label` attribute for accessibility, ensuring that
	 * the button's purpose is clear to screen reader users.
	 *
	 * @since 0.1.0
	 * @param bool $iconOnly Whether the button is icon-only.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setIconOnly( bool $iconOnly ): self {
		$this->iconOnly = $iconOnly;

		return $this;
	}

	/**
	 * Set whether the button is disabled.
	 *
	 * This method disables the button, preventing any interaction.
	 * A disabled button appears inactive and cannot be clicked.
	 *
	 * Example usage:
	 *
	 *     $button->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the button is disabled.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the button element.
	 *
	 * This method allows custom HTML attributes to be added to the button element, such as `id`, `data-*`, `aria-*`,
	 * or any other valid attributes. These attributes can be used to integrate the button with JavaScript, enhance
	 * accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $button->setAttributes([
	 *         'id' => 'submit-button',
	 *         'data-toggle' => 'modal',
	 *         'aria-label' => 'Submit Form'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set the href for the button.
	 *
	 * If set, the button will render as an <a> element styled like a button.
	 *
	 * @param string|null $href The href for the button link.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setHref( ?string $href ): self {
		$this->href = $href;

		return $this;
	}

	/**
	 * Get the href for the button.
	 *
	 * If an href is provided, the button will be rendered as an <a> element
	 * styled to look like a button instead of a native <button>.
	 *
	 * @return string|null
	 */
	public function getHref(): ?string {
		return $this->href;
	}

}
