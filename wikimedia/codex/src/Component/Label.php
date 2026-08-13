<?php
declare( strict_types = 1 );

/**
 * Label.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Label` class, responsible for managing
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
use Wikimedia\Codex\Renderer\LabelRenderer;

/**
 * Label
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Label extends Component {
	private ?string $id = null;

	public function __construct(
		LabelRenderer $renderer,
		private string|HtmlSnippet $labelText,
		private string $inputId,
		private bool $optional,
		private bool $visuallyHidden,
		private bool $isLegend,
		private string|HtmlSnippet $description,
		private ?string $descriptionId,
		private bool $disabled,
		private ?string $iconClass,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the ID of the label element.
	 *
	 * @since 0.1.0
	 * @return string|null The ID of the label, or null if not set.
	 */
	public function getId(): ?string {
		return $this->id;
	}

	/**
	 * Get the text displayed inside the label.
	 *
	 * This method returns the text displayed inside the label. The label text provides
	 * a descriptive title for the associated input field.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The text of the label.
	 */
	public function getLabelText(): string|HtmlSnippet {
		return $this->labelText;
	}

	/**
	 * Get the ID of the input/control this label is associated with.
	 *
	 * This method returns the ID of the input element that this label is associated with. The ID
	 * is crucial for linking the label to its corresponding input, ensuring accessibility.
	 *
	 * @since 0.1.0
	 * @return string The ID of the input element.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Check if the associated input field is optional.
	 *
	 * This method returns a boolean indicating whether the associated input field is optional.
	 * If true, an "(optional)" flag is typically displayed next to the label text.
	 *
	 * @since 0.1.0
	 * @return bool True if the input field is optional, false otherwise.
	 */
	public function isOptional(): bool {
		return $this->optional;
	}

	/**
	 * Check if the label is visually hidden but accessible to screen readers.
	 *
	 * This method returns a boolean indicating whether the label is visually hidden
	 * while still being accessible to screen readers. This is useful for forms where
	 * labels need to be accessible but not displayed.
	 *
	 * @since 0.1.0
	 * @return bool True if the label is visually hidden, false otherwise.
	 */
	public function isVisuallyHidden(): bool {
		return $this->visuallyHidden;
	}

	/**
	 * Check if the label is rendered as a `<legend>` element.
	 *
	 * This method returns a boolean indicating whether the label is rendered as a `<legend>`
	 * element, typically used within a `<fieldset>` for grouping related inputs.
	 *
	 * @since 0.1.0
	 * @return bool True if the label is rendered as a `<legend>`, false otherwise.
	 */
	public function isLegend(): bool {
		return $this->isLegend;
	}

	/**
	 * Get the description text associated with the label.
	 *
	 * This method returns the description text that provides additional information about the
	 * input field. The description is linked to the input via the `aria-describedby` attribute.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The description text for the label.
	 */
	public function getDescription(): string|HtmlSnippet {
		return $this->description;
	}

	/**
	 * Get the ID of the description element.
	 *
	 * This method returns the ID of the description element, which is useful for associating
	 * the description with an input via the `aria-describedby` attribute.
	 *
	 * @since 0.1.0
	 * @return string|null The ID for the description element, or null if not set.
	 */
	public function getDescriptionId(): ?string {
		return $this->descriptionId;
	}

	/**
	 * Check if the label is for a disabled field or input.
	 *
	 * This method returns a boolean indicating whether the label is associated with a disabled
	 * input field, applying the appropriate styles.
	 *
	 * @since 0.1.0
	 * @return bool True if the label is for a disabled input, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Get the icon class used before the label text.
	 *
	 * This method returns the CSS class for the icon displayed before the label text, if applicable.
	 * The icon enhances the visual appearance of the label.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the icon, or null if no icon is set.
	 */
	public function getIconClass(): ?string {
		return $this->iconClass;
	}

	/**
	 * Get the additional HTML attributes for the label element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied
	 * to the label element. These attributes can be used for customization or accessibility.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the label's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the label element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the label text.
	 *
	 * This method specifies the text that will be displayed inside the label.
	 * The label text provides a descriptive title for the associated input field.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $labelText The text of the label.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setLabelText( string|HtmlSnippet $labelText ): self {
		$this->labelText = $labelText;

		return $this;
	}

	/**
	 * Set the ID of the input/control this label is associated with.
	 *
	 * This method sets the 'for' attribute of the label, linking it to an input element.
	 * This connection is important for accessibility and ensures that clicking the label focuses the input.
	 *
	 * Example usage:
	 *
	 *     $label->setInputId('username');
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID of the input element.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set the optional flag.
	 *
	 * This method indicates whether the associated input field is optional.
	 * If true, an "(optional)" flag will be displayed next to the label text.
	 *
	 * Example usage:
	 *
	 *     $label->setOptionalFlag(true);
	 *
	 * @since 0.1.0
	 * @param bool $optional Whether the label is for an optional input.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setOptional( bool $optional ): self {
		$this->optional = $optional;

		return $this;
	}

	/**
	 * Set whether the label should be visually hidden.
	 *
	 * This method determines whether the label should be visually hidden while still being accessible to screen
	 * readers. Useful for forms where labels need to be read by assistive technologies but not displayed.
	 *
	 * Example usage:
	 *
	 *     $label->setVisuallyHidden(true);
	 *
	 * @since 0.1.0
	 * @param bool $visuallyHidden Whether the label should be visually hidden.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setVisuallyHidden( bool $visuallyHidden ): self {
		$this->visuallyHidden = $visuallyHidden;

		return $this;
	}

	/**
	 * Set whether this component should output a `<legend>` element.
	 *
	 * This method determines whether the label should be rendered as a `<legend>` element,
	 * typically used within a `<fieldset>` for grouping related inputs.
	 *
	 * Example usage:
	 *
	 *     $label->setIsLegend(true);
	 *
	 * @since 0.1.0
	 * @param bool $isLegend Whether to render the label as a `<legend>`.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setIsLegend( bool $isLegend ): self {
		$this->isLegend = $isLegend;

		return $this;
	}

	/**
	 * Set the description text for the label.
	 *
	 * This method adds a short description below the label, providing additional information about the input field.
	 * The description is linked to the input via the `aria-describedby` attribute for accessibility.
	 *
	 * Example usage:
	 *
	 *     $label->setDescriptionText('Please enter a valid email.');
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $description The description text for the label.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setDescription( string|HtmlSnippet $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set the ID of the description element.
	 *
	 * This method sets the ID attribute for the description element, which is useful for associating
	 * the description with an input via the `aria-describedby` attribute.
	 *
	 * Example usage:
	 *
	 *     $label->setDescriptionId('username-desc');
	 *
	 * @since 0.1.0
	 * @param string|null $descriptionId The ID for the description element.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setDescriptionId( ?string $descriptionId ): self {
		$this->descriptionId = $descriptionId ?: null;

		return $this;
	}

	/**
	 * Set whether the label is for a disabled field or input.
	 *
	 * This method marks the label as associated with a disabled input, applying the appropriate styles.
	 *
	 * Example usage:
	 *
	 *     $label->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the label is for a disabled input.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set an icon before the label text.
	 *
	 * This method allows for an icon to be displayed before the label text, specified by a CSS class.
	 * The icon enhances the visual appearance of the label.
	 *
	 * Example usage:
	 *
	 *     $label->setIcon('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string|null $iconClass The CSS class for the icon.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setIconClass( ?string $iconClass ): self {
		$this->iconClass = $iconClass;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the label element.
	 *
	 * This method allows custom HTML attributes to be added to the label element, such as `id`, `class`, or `data-*`
	 * attributes. These attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $label->setAttributes(['class' => 'custom-label-class', 'data-info' => 'additional-info']);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}

		return $this;
	}
}
