<?php
declare( strict_types = 1 );

/**
 * Field.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Field` class, responsible for managing
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
use Wikimedia\Codex\Renderer\FieldRenderer;

/**
 * Field
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Field extends Component {
	private string $id = '';

	public function __construct(
		FieldRenderer $renderer,
		private ?Label $label,
		private bool $isFieldset,
		private array $fields,
		private array $attributes
	) {
		foreach ( $this->fields as $field ) {
			if ( is_string( $field ) ) {
				trigger_error(
					"Passing raw HTML strings to setFields() is deprecated and will be removed in 1.0.0. " .
					"Please use HtmlSnippet objects instead.",
					E_USER_DEPRECATED
				);
			}
		}
		parent::__construct( $renderer );
	}

	/**
	 * Get the fieldset or div's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the fieldset or div element. The ID is useful for targeting
	 * the field with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the fieldset or div element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the label of the field.
	 *
	 * This method returns the label object associated with the field. The label provides a descriptive
	 * name that helps users understand the purpose of the field.
	 *
	 * @since 0.1.0
	 * @return ?Label The label of the field.
	 */
	public function getLabel(): ?Label {
		return $this->label;
	}

	/**
	 * Check if the fields are wrapped in a fieldset with a legend.
	 *
	 * This method returns a boolean indicating whether the fields are wrapped in a `<fieldset>`
	 * element with a `<legend>`. If false, the fields are wrapped in a `<div>` with a `<label>`.
	 *
	 * @since 0.1.0
	 * @return bool True if the fields are wrapped in a fieldset, false otherwise.
	 */
	public function isFieldset(): bool {
		return $this->isFieldset;
	}

	/**
	 * Get the fields included within the fieldset or div.
	 *
	 * This method returns an array of fields (as HTML strings) that are included
	 * within the fieldset or div.
	 *
	 * @since 0.1.0
	 * @return array<Component|HtmlSnippet|string> The fields included in the fieldset or div.
	 */
	public function getFields(): array {
		return $this->fields;
	}

	/**
	 * Get the additional HTML attributes for the fieldset or div element.
	 *
	 * This method returns an associative array of additional HTML attributes
	 * that are applied to the fieldset or div element.
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
	 * @param string $id The ID for the field element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the label for the field.
	 *
	 * This method accepts a Label object which provides a descriptive label for the field.
	 *
	 * @since 0.1.0
	 * @param Label $label The Label object for the field.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set whether the fields should be wrapped in a fieldset with a legend.
	 *
	 * When set to `true`, this method wraps the fields in a `<fieldset>` element with a `<legend>`.
	 * If set to `false`, the fields are wrapped in a `<div>` with a `<label>` instead.
	 *
	 * @since 0.1.0
	 * @param bool $isFieldset Whether to wrap fields in a fieldset.
	 * @return $this Returns the Field instance for method chaining.
	 */
	public function setIsFieldset( bool $isFieldset ): self {
		$this->isFieldset = $isFieldset;

		return $this;
	}

	/**
	 * Set the fields within the fieldset.
	 *
	 * This method accepts an array of fields to be included within the fieldset or a `<div>`.
	 * It allows grouping of related fields together under a common legend or label for better organization.
	 *
	 * Fields may be Component objects, HtmlSnippet objects, or raw HTML strings. Passing in strings
	 * is deprecated and will be removed in 1.0.0.
	 *
	 * @since 0.1.0
	 * @param array<Component|HtmlSnippet|string> $fields The array of fields to include in the fieldset.
	 * @return $this Returns the Field instance for method chaining.
	 */
	public function setFields( array $fields ): self {
		foreach ( $fields as $field ) {
			if ( is_string( $field ) ) {
				trigger_error(
					"Passing raw HTML strings to setFields() is deprecated and will be removed in 1.0.0. " .
					"Please use HtmlSnippet objects instead.",
					E_USER_DEPRECATED
				);
			}
		}
		$this->fields = $fields;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the fieldset or div element.
	 *
	 * This method allows custom HTML attributes to be added to the fieldset or div element, such as `id`, `data-*`,
	 * `aria-*`, or any other valid attributes. These attributes can be used to further customize the fieldset or div,
	 * enhance accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $field->setAttributes([
	 *         'id' => 'user-info-fieldset',
	 *         'data-category' => 'user-data',
	 *         'aria-labelledby' => 'legend-user-info'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Field instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}

		return $this;
	}
}
