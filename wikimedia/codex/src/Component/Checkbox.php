<?php
declare( strict_types = 1 );

/**
 * Checkbox.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Checkbox` class, responsible for managing
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
use Wikimedia\Codex\Renderer\CheckboxRenderer;

/**
 * Checkbox
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Checkbox extends Component {
	public function __construct(
		CheckboxRenderer $renderer,
		private string $inputId,
		private string $name,
		private ?Label $label,
		private string $value,
		private bool $checked,
		private bool $disabled,
		private bool $inline,
		private array $inputAttributes,
		private array $wrapperAttributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the ID of the checkbox input.
	 *
	 * This method returns the unique identifier used for the checkbox input element.
	 * The ID is essential for associating the checkbox with its label and for targeting with JavaScript or CSS.
	 *
	 * @since 0.1.0
	 * @return string The ID of the checkbox input.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Get the name attribute of the checkbox input.
	 *
	 * This method returns the name attribute, which is used to identify the checkbox when the form is submitted.
	 * The name is especially important when handling multiple checkboxes as part of a group.
	 *
	 * @since 0.1.0
	 * @return string The name attribute of the checkbox input.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the label of the checkbox input.
	 *
	 * This method returns the label text associated with the checkbox. The label provides a descriptive
	 * name that helps users understand the purpose of the checkbox.
	 *
	 * @since 0.1.0
	 * @return ?Label The label of the checkbox input.
	 */
	public function getLabel(): ?Label {
		return $this->label;
	}

	/**
	 * Get the value of the checkbox input.
	 *
	 * This method returns the value submitted when the checkbox is checked and the form is submitted.
	 * The value is important for differentiating between various checkboxes in a group.
	 *
	 * @since 0.1.0
	 * @return string The value of the checkbox input.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Check if the checkbox is selected.
	 *
	 * This method returns a boolean indicating whether the checkbox is checked by default.
	 *
	 * @since 0.1.0
	 * @return bool True if the checkbox is checked, false otherwise.
	 */
	public function isChecked(): bool {
		return $this->checked;
	}

	/**
	 * Check if the checkbox is disabled.
	 *
	 * This method returns a boolean indicating whether the checkbox is disabled, which prevents user interaction.
	 *
	 * @since 0.1.0
	 * @return bool True if the checkbox is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Check if the checkbox is displayed inline.
	 *
	 * This method returns a boolean indicating whether the checkbox
	 * and its label are displayed inline with other elements.
	 *
	 * @since 0.1.0
	 * @return bool True if the checkbox is displayed inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get the additional HTML attributes for the checkbox input.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied
	 * to the checkbox element. These attributes can be used to customize the checkboxes behavior
	 * and appearance or to enhance its integration with JavaScript.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getInputAttributes(): array {
		return $this->inputAttributes;
	}

	/**
	 * Get additional HTML attributes for the outer wrapper element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied to the outer wrapper element,
	 * enhancing its behavior or styling.
	 *
	 * @since 0.1.0
	 * @return array The associative array of HTML attributes for the wrapper element.
	 */
	public function getWrapperAttributes(): array {
		return $this->wrapperAttributes;
	}

	/**
	 * Set the ID for the checkbox input.
	 *
	 * The ID is a unique identifier for the checkbox input element. It is used to associate the input
	 * with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID for the checkbox input.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set the name for the checkbox input.
	 *
	 * The name attribute is used to identify form data after the form is submitted. It is crucial when
	 * handling multiple checkboxes as part of a group or when submitting form data via POST or GET requests.
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the checkbox input.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the label for the checkbox input.
	 *
	 * This method accepts a Label object which provides a descriptive label for the checkbox.
	 *
	 * @since 0.1.0
	 * @param Label $label The Label object for the checkbox.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set the value for the checkbox input.
	 *
	 * The value is the submitted data when the checkbox is checked and the form is submitted.
	 * This is particularly important when dealing with groups of checkboxes where each needs a distinct value.
	 *
	 * @since 0.1.0
	 * @param string $value The value for the checkbox input.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set whether the checkbox should be checked.
	 *
	 * This method determines whether the checkbox is selected by default. If set to `true`,
	 * the checkbox will be rendered in a checked state, otherwise, it will be unchecked.
	 *
	 * @since 0.1.0
	 * @param bool $checked Whether the checkbox should be checked.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setChecked( bool $checked ): self {
		$this->checked = $checked;

		return $this;
	}

	/**
	 * Set whether the checkbox should be disabled.
	 *
	 * This method determines whether the checkbox is disabled, preventing user interaction.
	 * A disabled checkbox cannot be checked or unchecked by the user and is typically styled to appear inactive.
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the checkbox should be disabled.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set whether the checkbox should display inline.
	 *
	 * This method determines whether the checkbox and its label should be displayed inline with other elements.
	 * Inline checkboxes are typically used when multiple checkboxes need to appear on the same line.
	 *
	 * @since 0.1.0
	 * @param bool $inline Indicates whether the checkbox should be displayed inline.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the root checkbox input.
	 *
	 * This method allows custom HTML attributes to be added to the checkbox element, such as `id`, `data-*`, `aria-*`,
	 * or any other valid attributes. These attributes can be used to integrate the checkbox with JavaScript, enhance
	 * accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * @since 0.1.0
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setInputAttributes( array $inputAttributes ): self {
		foreach ( $inputAttributes as $key => $value ) {
			$this->inputAttributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer wrapper element.
	 *
	 * This method allows custom HTML attributes to be added to the outer wrapper element,
	 * enhancing its behavior or styling.
	 *
	 * Example usage:
	 *
	 *     $textInput->setWrapperAttributes(['id' => 'custom-wrapper']);
	 *
	 * @since 0.1.0
	 * @param array $wrapperAttributes An associative array of HTML attributes for the wrapper element.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}

		return $this;
	}
}
