<?php
declare( strict_types = 1 );

/**
 * TextInput.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `TextInput` class, responsible for managing
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
use Wikimedia\Codex\Renderer\TextInputRenderer;

/**
 * TextInput
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class TextInput extends Component {

	/**
	 * Supported input types for the text input.
	 */
	public const TEXT_INPUT_TYPES = [
		'text',
		'search',
		'number',
		'email',
		'month',
		'password',
		'tel',
		'url',
		'week',
		'date',
		'datetime-local',
		'time',
	];

	/**
	 * Allowed validation statuses for the TextInput.
	 */
	public const ALLOWED_STATUS_TYPES = [
		'default',
		'error',
		'warning',
		'success',
	];

	public function __construct(
		TextInputRenderer $renderer,
		private string $type,
		private string $name,
		private string $value,
		private string $inputId,
		private string $placeholder,
		private bool $disabled,
		private string $status,
		private array $inputAttributes,
		private array $wrapperAttributes,
		private bool $hasStartIcon,
		private bool $hasEndIcon,
		private ?string $startIconClass,
		private ?string $endIconClass
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the type of the input field.
	 *
	 * This method returns the type attribute of the input field, which determines the type of data
	 * the input field accepts (e.g., 'text', 'email', 'password').
	 *
	 * @since 0.1.0
	 * @return string The type of the input field.
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * Get the name attribute of the input field.
	 *
	 * This method returns the name attribute of the input field, which is used to identify the input
	 * form control when submitting the form data.
	 *
	 * @since 0.1.0
	 * @return string The name attribute of the input field.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the value of the input field.
	 *
	 * This method returns the current value of the input field.
	 *
	 * @since 0.1.0
	 * @return string The value of the input field.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Get the placeholder text of the input field.
	 *
	 * This method returns the placeholder text, which is displayed when the input field is empty.
	 * It provides a hint to the user about what should be entered in the field.
	 *
	 * @since 0.1.0
	 * @return string The placeholder text of the input field.
	 */
	public function getPlaceholder(): string {
		return $this->placeholder;
	}

	/**
	 * Get the ID of the input field.
	 *
	 * This method returns the ID attribute of the input field, which is useful for linking
	 * the input field to a label or for other JavaScript interactions.
	 *
	 * @since 0.1.0
	 * @return string The ID of the input field.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Check if the input field has a start icon.
	 *
	 * This method returns a boolean value indicating whether the input field has an icon at the start.
	 *
	 * @since 0.1.0
	 * @return bool True if the input field has a start icon, false otherwise.
	 */
	public function hasStartIcon(): bool {
		return $this->hasStartIcon;
	}

	/**
	 * Check if the input field has an end icon.
	 *
	 * This method returns a boolean value indicating whether the input field has an icon at the end.
	 *
	 * @since 0.1.0
	 * @return bool True if the input field has an end icon, false otherwise.
	 */
	public function hasEndIcon(): bool {
		return $this->hasEndIcon;
	}

	/**
	 * Get the CSS class for the start icon.
	 *
	 * This method returns the CSS class that is applied to the start icon, which can be used to style
	 * the icon or apply a background image.
	 *
	 * @since 0.1.0
	 * @return ?string The CSS class for the start icon.
	 */
	public function getStartIconClass(): ?string {
		return $this->startIconClass;
	}

	/**
	 * Get the CSS class for the end icon.
	 *
	 * This method returns the CSS class that is applied to the end icon, which can be used to style
	 * the icon or apply a background image.
	 *
	 * @since 0.1.0
	 * @return ?string The CSS class for the end icon.
	 */
	public function getEndIconClass(): ?string {
		return $this->endIconClass;
	}

	/**
	 * Check if the input field is disabled.
	 *
	 * This method returns a boolean value indicating whether the input field is disabled, making it uneditable.
	 *
	 * @since 0.1.0
	 * @return bool True if the input field is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Get the validation status of the input.
	 *
	 * This method returns a string value indicating the current validation status, which is used to
	 * add a CSS class that can be used for special styles per status.
	 *
	 * @since 0.1.0
	 * @return string Validation status, e.g. 'default' or 'error'.
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * Get additional HTML attributes for the input element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied to the input element,
	 * such as `data-*`, `aria-*`, or any other valid attributes that enhance functionality or accessibility.
	 *
	 * @since 0.1.0
	 * @return array The associative array of HTML attributes for the input element.
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
	 * Set the type of the input field.
	 *
	 * This method sets the type attribute of the input field, which determines
	 * the type of data the input field accepts, such as 'text', 'email', 'password', etc.
	 *
	 * Example usage:
	 *
	 *     $textInput->setType('email');
	 *
	 * @since 0.1.0
	 * @param string $type The type of the input field (e.g., 'text', 'email').
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setType( string $type ): self {
		if ( !in_array( $type, self::TEXT_INPUT_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid input type: $type" );
		}
		$this->type = $type;

		return $this;
	}

	/**
	 * Set the name attribute for the input field.
	 *
	 * This method specifies the name attribute for the input field, which is used to identify
	 * the input form control when submitting the form data.
	 *
	 * Example usage:
	 *
	 *     $textInput->setName('email');
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the value attribute for the input field.
	 *
	 * This method specifies the value attribute for the input field, which represents the
	 * current value of the input field.
	 *
	 * Example usage:
	 *
	 *     $textInput->setValue('example@example.com');
	 *
	 * @since 0.1.0
	 * @param string $value The value of the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set the ID for the input field.
	 *
	 * This method sets the ID attribute for the input field, which is useful for linking
	 * the input field to a label or for other JavaScript interactions.
	 *
	 * Example usage:
	 *
	 *     $textInput->setInputId('email-input');
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID of the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set whether the input has a start icon.
	 *
	 * This method specifies whether the input field should have an icon at the start.
	 * The icon can be used to visually indicate the type of input expected.
	 *
	 * Example usage:
	 *
	 *     $textInput->setHasStartIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasStartIcon Indicates whether the input field has a start icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setHasStartIcon( bool $hasStartIcon ): self {
		$this->hasStartIcon = $hasStartIcon;

		return $this;
	}

	/**
	 * Set whether the input has an end icon.
	 *
	 * This method specifies whether the input field should have an icon at the end.
	 * The icon can be used to visually indicate additional functionality or context.
	 *
	 * Example usage:
	 *
	 *     $textInput->setHasEndIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasEndIcon Indicates whether the input field has an end icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setHasEndIcon( bool $hasEndIcon ): self {
		$this->hasEndIcon = $hasEndIcon;

		return $this;
	}

	/**
	 * Set whether the input is disabled.
	 *
	 * This method disables the input field, making it uneditable and visually distinct.
	 * The disabled attribute is useful for read-only forms or when the input is temporarily inactive.
	 *
	 * Example usage:
	 *
	 *     $textInput->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the input field should be disabled.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set the validation status for the input.
	 *
	 * Example usage:
	 *
	 *     $textInput->setStatus('error');
	 *
	 * @since 0.1.0
	 * @param string $status Current validation status.
	 * @return $this
	 */
	public function setStatus( string $status ): self {
		if ( !in_array( $status, self::ALLOWED_STATUS_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid status: $status" );
		}
		$this->status = $status;

		return $this;
	}

	/**
	 * Set the CSS class for the start icon.
	 *
	 * This method specifies the CSS class that will be applied to the start icon.
	 * The class can be used to style the icon or apply a background image.
	 *
	 * Example usage:
	 *
	 *     $textInput->setStartIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $startIconClass The CSS class for the start icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setStartIconClass( string $startIconClass ): self {
		$this->startIconClass = $startIconClass;

		return $this;
	}

	/**
	 * Set the CSS class for the end icon.
	 *
	 * This method specifies the CSS class that will be applied to the end icon.
	 * The class can be used to style the icon or apply a background image.
	 *
	 * Example usage:
	 *
	 *     $textInput->setEndIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $endIconClass The CSS class for the end icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setEndIconClass( string $endIconClass ): self {
		$this->endIconClass = $endIconClass;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the input element.
	 *
	 * This method allows custom HTML attributes to be added to the input element, such as `data-*`,
	 * `aria-*`, or any other valid attributes that enhance functionality or accessibility.
	 *
	 * Example usage:
	 *
	 *     $textInput->setInputAttributes(['data-test' => 'value']);
	 *
	 * @since 0.1.0
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the TextInput instance for method chaining.
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
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set the placeholder text for the input element.
	 *
	 * This method sets the placeholder text, which is displayed when the input field is empty.
	 * It provides a hint to the user about what should be entered in the field.
	 *
	 * Example usage:
	 *
	 *     $textInput->setPlaceholder('johndoe@example.com');
	 *
	 * @since 0.1.0
	 * @param string $placeholder The placeholder text for the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setPlaceholder( string $placeholder ): self {
		$this->placeholder = $placeholder;

		return $this;
	}
}
