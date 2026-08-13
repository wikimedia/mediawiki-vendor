<?php
declare( strict_types = 1 );

/**
 * ToggleSwitch.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `ToggleSwitch` class, responsible for managing
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
use Wikimedia\Codex\Renderer\ToggleSwitchRenderer;

/**
 * ToggleSwitch
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class ToggleSwitch extends Component {
	public function __construct(
		ToggleSwitchRenderer $renderer,
		private string $inputId,
		private string $name,
		private ?Label $label,
		private string $value,
		private bool $checked,
		private bool $disabled,
		private array $inputAttributes,
		private array $wrapperAttributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the ID for the toggle switch input.
	 *
	 * This method returns the unique identifier for the toggle switch input element.
	 * The ID is used to associate the input with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.1.0
	 * @return string The ID for the toggle switch input.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Get the name attribute of the toggle switch input.
	 *
	 * This method returns the name attribute, which is used to identify the toggle switch when the form is submitted.
	 *
	 * @since 0.1.0
	 * @return string The name attribute of the toggle switch input.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the value associated with the toggle switch input.
	 *
	 * This method returns the value submitted when the toggle switch is checked and the form is submitted.
	 *
	 * @since 0.1.0
	 * @return string The value of the toggle switch input.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Get the label object for the toggle switch.
	 *
	 * This method returns the label object that provides a descriptive label for the toggle switch.
	 * The label is crucial for accessibility and usability.
	 *
	 * @since 0.1.0
	 * @return ?Label The label object for the toggle switch.
	 */
	public function getLabel(): ?Label {
		return $this->label;
	}

	/**
	 * Check if the toggle switch is checked by default.
	 *
	 * This method returns a boolean value indicating whether the toggle switch is checked by default.
	 * If true, the toggle switch is rendered in a checked state.
	 *
	 * @since 0.1.0
	 * @return bool True if the toggle switch is checked, false otherwise.
	 */
	public function isChecked(): bool {
		return $this->checked;
	}

	/**
	 * Check if the toggle switch is disabled.
	 *
	 * This method returns a boolean value indicating whether the toggle switch is disabled,
	 * preventing user interaction. A disabled toggle switch cannot be checked or unchecked by the user.
	 *
	 * @since 0.1.0
	 * @return bool True if the toggle switch is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Get the additional HTML attributes for the toggle switch input.
	 *
	 * This method returns an associative array of custom HTML attributes for the toggle switch input element,
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
	 * Set the ID for the toggle switch input.
	 *
	 * @param string $inputId The ID for the toggle switch input.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;
		return $this;
	}

	/**
	 * Set the name for the toggle switch input.
	 *
	 * @param string $name The name attribute for the toggle switch input.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set the value for the toggle switch input.
	 *
	 * @param string $value The value associated with the toggle switch input.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;
		return $this;
	}

	/**
	 * Set the label for the toggle switch.
	 *
	 * @param Label $label The label object for the toggle switch.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;
		return $this;
	}

	/**
	 * Set whether the toggle switch should be checked by default.
	 *
	 * @param bool $checked Whether the toggle switch should be checked by default.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setChecked( bool $checked ): self {
		$this->checked = $checked;
		return $this;
	}

	/**
	 * Set whether the toggle switch should be disabled.
	 *
	 * @param bool $disabled Whether the toggle switch should be disabled.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;
		return $this;
	}

	/**
	 * Set additional HTML attributes for the input element.
	 *
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setInputAttributes( array $inputAttributes ): self {
		$this->inputAttributes = $inputAttributes;
		return $this;
	}

	/**
	 * Set additional HTML attributes for the wrapper element.
	 *
	 * @param array $wrapperAttributes An associative array of HTML attributes for the wrapper element.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		$this->wrapperAttributes = $wrapperAttributes;
		return $this;
	}
}
