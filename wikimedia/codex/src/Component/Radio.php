<?php
declare( strict_types = 1 );

/**
 * Radio.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Radio` class, responsible for managing
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
use Wikimedia\Codex\Renderer\RadioRenderer;

/**
 * Radio
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Radio extends Component {
	public function __construct(
		RadioRenderer $renderer,
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
	 * Get the ID for the radio input.
	 *
	 * This method returns the unique identifier for the radio input element. The ID is used to associate the input
	 * with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.7.1
	 * @return string The ID for the radio input.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Get the name attribute for the radio input.
	 *
	 * This method returns the name attribute used to identify form data after the form is submitted.
	 * It is crucial when handling groups of radio buttons where only one option can be selected at a time.
	 *
	 * @since 0.1.0
	 * @return string The name attribute for the radio input.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the label object for the radio input.
	 *
	 * This method returns the label object that provides a descriptive label for the radio button.
	 * The label is crucial for accessibility and usability.
	 *
	 * @since 0.1.0
	 * @return ?Label The label object for the radio button.
	 */
	public function getLabel(): ?Label {
		return $this->label;
	}

	/**
	 * Get the value associated with the radio input.
	 *
	 * This method returns the value submitted when the radio button is selected and the form is submitted.
	 * This is particularly important when dealing with groups of radio buttons where each needs a distinct value.
	 *
	 * @since 0.1.0
	 * @return string The value of the radio input.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Check if the radio is selected by default.
	 *
	 * This method returns a boolean value indicating whether the radio button is selected by default.
	 * If true, the radio button is rendered in a checked state.
	 *
	 * @since 0.1.0
	 * @return bool True if the radio button is checked, false otherwise.
	 */
	public function isChecked(): bool {
		return $this->checked;
	}

	/**
	 * Check if the radio is disabled.
	 *
	 * This method returns a boolean value indicating whether the radio button is disabled,
	 * preventing user interaction. A disabled radio button cannot be selected or deselected by the user.
	 *
	 * @since 0.1.0
	 * @return bool True if the radio button is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Check if the radio button should be displayed inline.
	 *
	 * This method returns a boolean value indicating whether the radio button and its label are displayed
	 * inline with other elements. Inline radio buttons are typically used when multiple radio buttons need
	 * to appear on the same line.
	 *
	 * @since 0.1.0
	 * @return bool True if the radio button is displayed inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get the additional HTML attributes for the radio input.
	 *
	 * This method returns an associative array of custom HTML attributes for the radio input element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes.
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
	 * Set the ID for the radio input.
	 *
	 * The ID is a unique identifier for the radio input element. It is used to associate the input
	 * with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.7.1
	 * @param string $inputId The ID for the radio input.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set the name for the radio input.
	 *
	 * The name attribute is used to identify form data after the form is submitted. It is crucial when
	 * handling groups of radio buttons where only one option can be selected at a time.
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the radio input.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the label for the radio input.
	 *
	 * This method accepts a Label object which provides a descriptive label for the radio.
	 *
	 * @since 0.1.0
	 * @param Label $label The Label object for the radio.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set the value for the radio input.
	 *
	 * The value is the data submitted when the radio button is selected and the form is submitted.
	 * This is particularly important when dealing with groups of radio buttons where each needs a distinct value.
	 *
	 * @since 0.1.0
	 * @param string $value The value for the radio input.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set whether the radio should be checked.
	 *
	 * This method determines whether the radio button is selected by default. If set to `true`,
	 * the radio button will be rendered in a checked state, otherwise, it will be unchecked.
	 *
	 * @since 0.1.0
	 * @param bool $checked Whether the radio button should be checked.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setChecked( bool $checked ): self {
		$this->checked = $checked;

		return $this;
	}

	/**
	 * Set whether the radio should be disabled.
	 *
	 * This method determines whether the radio button is disabled, preventing user interaction.
	 * A disabled radio button cannot be selected or deselected by the user and is typically styled to appear inactive.
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the radio button should be disabled.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set whether the radio button should be displayed inline.
	 *
	 * This method determines whether the radio button and its label should be displayed inline with other elements.
	 * Inline radio buttons are typically used when multiple radio buttons need to appear on the same line.
	 *
	 * @since 0.1.0
	 * @param bool $inline Indicates whether the radio button should be displayed inline.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the radio input.
	 *
	 * This method allows custom HTML attributes to be added to the radio input element, such as `id`, `data-*`,
	 * `aria-*`, or any other valid attributes. These attributes can be used to integrate the radio button with
	 * JavaScript, enhance accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $radio->setInputAttributes([
	 *         'id' => 'radio-button-1',
	 *         'data-toggle' => 'radio-toggle',
	 *         'aria-label' => 'Radio Button 1'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the Radio instance for method chaining.
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
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}

		return $this;
	}
}
