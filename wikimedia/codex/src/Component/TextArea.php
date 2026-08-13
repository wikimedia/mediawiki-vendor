<?php
declare( strict_types = 1 );

/**
 * TextArea.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `TextArea` class, responsible for managing
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
use Wikimedia\Codex\Renderer\TextAreaRenderer;

/**
 * TextArea
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class TextArea extends Component {
	/**
	 * Allowed values for the status type.
	 */
	public const ALLOWED_STATUS_TYPES = [
		'notice',
		'warning',
		'error',
		'success',
	];

	public function __construct(
		TextAreaRenderer $renderer,
		private string $name,
		private string $value,
		private string $inputId,
		private array $inputAttributes,
		private array $wrapperAttributes,
		private bool $disabled,
		private bool $readonly,
		private bool $hasStartIcon,
		private bool $hasEndIcon,
		private string $startIconClass,
		private string $endIconClass,
		private string $placeholder,
		private string $status
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the HTML ID for the textarea.
	 *
	 * This method returns the HTML `id` attribute value for the textarea element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the textarea.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Get the name attribute of the textarea element.
	 *
	 * This method returns the name attribute of the textarea, which is used to identify
	 * the textarea form control when submitting the form data.
	 *
	 * @since 0.1.0
	 * @return string The name attribute of the textarea.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the value of the textarea element.
	 *
	 * This method returns the current content inside the textarea, which could be
	 * the default value or any content that was previously set.
	 *
	 * @since 0.1.0
	 * @return string The value of the textarea.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Get the additional HTML attributes for the textarea element.
	 *
	 * This method returns an associative array of custom HTML attributes applied to the textarea.
	 * These attributes can be used to enhance accessibility or integrate with JavaScript.
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
	 * @return array The additional attributes as an array.
	 */
	public function getWrapperAttributes(): array {
		return $this->wrapperAttributes;
	}

	/**
	 * Check if the textarea is disabled.
	 *
	 * This method returns a boolean indicating whether the textarea is disabled.
	 * A disabled textarea is not editable and has a distinct visual appearance.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Check if the textarea is read-only.
	 *
	 * This method returns a boolean indicating whether the textarea is read-only.
	 * A read-only textarea cannot be modified by the user but can be selected and copied.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea is read-only, false otherwise.
	 */
	public function isReadonly(): bool {
		return $this->readonly;
	}

	/**
	 * Check if the textarea has a start icon.
	 *
	 * This method returns a boolean indicating whether the textarea includes an icon at the start.
	 * The start icon is typically used to visually indicate the type of input expected.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea has a start icon, false otherwise.
	 */
	public function hasStartIcon(): bool {
		return $this->hasStartIcon;
	}

	/**
	 * Check if the textarea has an end icon.
	 *
	 * This method returns a boolean indicating whether the textarea includes an icon at the end.
	 * The end icon is typically used to visually indicate additional functionality or context.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea has an end icon, false otherwise.
	 */
	public function hasEndIcon(): bool {
		return $this->hasEndIcon;
	}

	/**
	 * Get the CSS class for the start icon.
	 *
	 * This method returns the CSS class applied to the start icon. This class can be used
	 * to style the icon or apply a background image.
	 *
	 * @since 0.1.0
	 * @return string The CSS class for the start icon.
	 */
	public function getStartIconClass(): string {
		return $this->startIconClass;
	}

	/**
	 * Get the CSS class for the end icon.
	 *
	 * This method returns the CSS class applied to the end icon. This class can be used
	 * to style the icon or apply a background image.
	 *
	 * @since 0.1.0
	 * @return string The CSS class for the end icon.
	 */
	public function getEndIconClass(): string {
		return $this->endIconClass;
	}

	/**
	 * Get the placeholder text of the textarea element.
	 *
	 * This method returns the placeholder text displayed inside the textarea when it is empty.
	 * The placeholder provides a hint to the user about the expected input.
	 *
	 * @since 0.1.0
	 * @return string The placeholder text of the textarea.
	 */
	public function getPlaceholder(): string {
		return $this->placeholder;
	}

	/**
	 * Get the validation status of the textarea.
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
	 * Set the TextArea HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID for the TextArea element.
	 * @return $this
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * @deprecated Use setInputId() instead
	 */
	public function setId( string $id ): self {
		return $this->setInputId( $id );
	}

	/**
	 * Set the name attribute for the textarea element.
	 *
	 * This method sets the name attribute for the textarea element, which is used to identify
	 * the textarea form control when submitting the form data.
	 *
	 * Example usage:
	 *
	 *     $textArea->setName('description');
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the textarea.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the default content inside the textarea.
	 *
	 * This method sets the initial content that will be displayed inside the textarea.
	 * The content can be prefilled with a default value if necessary.
	 *
	 * Example usage:
	 *
	 *     $textArea->setValue('Default content...');
	 *
	 * @since 0.1.0
	 * @param mixed $value The content to be displayed inside the textarea.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setValue( $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the textarea element.
	 *
	 * This method allows custom HTML attributes to be added to the textarea element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes that enhance functionality or accessibility.
	 *
	 * Example usage:
	 *
	 *     $textArea->setinputAttributes([
	 *         'id' => 'text-area-id',
	 *         'data-category' => 'input',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $inputAttributes An associative array of HTML attributes for the textarea element.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setinputAttributes( array $inputAttributes ): self {
		foreach ( $inputAttributes as $key => $value ) {
			$this->inputAttributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * @deprecated Use setInputAttributes() instead
	 */
	public function setTextAreaAttributes( array $textAreaAttributes ): self {
		return $this->setInputAttributes( $textAreaAttributes );
	}

	/**
	 * Set additional HTML attributes for the outer wrapper element.
	 *
	 * This method allows custom HTML attributes to be added to the outer wrapper element,
	 * enhancing its behavior or styling.
	 *
	 * Example usage:
	 *
	 *        $textArea->setWrapperAttributes(['id' => 'custom-wrapper']);
	 *
	 * @since 0.1.0
	 * @param array $wrapperAttributes An associative array of HTML attributes.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Set the disabled state for the textarea.
	 *
	 * This method disables the textarea, making it uneditable and visually distinct.
	 * The disabled attribute is useful for read-only forms or when the input is temporarily inactive.
	 *
	 * Example usage:
	 *
	 *     $textArea->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the textarea should be disabled.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set the readonly state for the textarea.
	 *
	 * This method makes the textarea read-only, meaning users can view the content
	 * but cannot modify it. The readonly attribute is useful when displaying static content.
	 *
	 * Example usage:
	 *
	 *     $textArea->setReadonly(true);
	 *
	 * @since 0.1.0
	 * @param bool $readonly Indicates whether the textarea should be read-only.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setReadonly( bool $readonly ): self {
		$this->readonly = $readonly;

		return $this;
	}

	/**
	 * Set whether the textarea has a start icon.
	 *
	 * This method specifies whether the textarea should have an icon at the start.
	 * The icon can be used to visually indicate the type of input expected in the textarea.
	 *
	 * Example usage:
	 *
	 *     $textArea->setHasStartIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasStartIcon Indicates whether the textarea has a start icon.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setHasStartIcon( bool $hasStartIcon ): self {
		$this->hasStartIcon = $hasStartIcon;

		return $this;
	}

	/**
	 * Set whether the textarea has an end icon.
	 *
	 * This method specifies whether the textarea should have an icon at the end.
	 * The icon can be used to visually indicate additional functionality or context related to the input.
	 *
	 * Example usage:
	 *
	 *     $textArea->setHasEndIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasEndIcon Indicates whether the textarea has an end icon.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setHasEndIcon( bool $hasEndIcon ): self {
		$this->hasEndIcon = $hasEndIcon;

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
	 *     $textArea->setStartIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $startIconClass The CSS class for the start icon.
	 * @return $this Returns the TextArea instance for method chaining.
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
	 *     $textArea->setEndIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $endIconClass The CSS class for the end icon.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setEndIconClass( string $endIconClass ): self {
		$this->endIconClass = $endIconClass;

		return $this;
	}

	/**
	 * Set the placeholder text for the textarea element.
	 *
	 * This method specifies the placeholder text that will be displayed inside the textarea
	 * when it is empty. The placeholder provides a hint to the user about the expected input.
	 *
	 * Example usage:
	 *
	 *     $textArea->setPlaceholder('Rationale...');
	 *
	 * @since 0.1.0
	 * @param string $placeholder The placeholder text for the textarea.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setPlaceholder( string $placeholder ): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	/**
	 * Set the validation status for the textarea.
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
}
