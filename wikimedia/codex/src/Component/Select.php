<?php
declare( strict_types = 1 );

/**
 * Select.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Select` class, responsible for managing
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
use Wikimedia\Codex\Renderer\SelectRenderer;

/**
 * Select
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Select extends Component {
	private string $id = '';

	public function __construct(
		SelectRenderer $renderer,
		private array $options,
		private array $optGroups,
		private ?string $selectedOption,
		private bool $disabled,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the Select's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the select element, which is used
	 * for identifying the select component in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Select element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the options for the select element.
	 *
	 * This method returns an associative array where the keys are the option values
	 * and the values are the display text shown to the user.
	 *
	 * @since 0.1.0
	 * @return array The associative array of options for the select element.
	 */
	public function getOptions(): array {
		return $this->options;
	}

	/**
	 * Get the optGroups for the select element.
	 *
	 * This method returns an associative array of optGroups, where each key is a label for a group
	 * and the value is an array of options within that group.
	 *
	 * @since 0.1.0
	 * @return array The associative array of optGroups for the select element.
	 */
	public function getOptGroups(): array {
		return $this->optGroups;
	}

	/**
	 * Get the additional HTML attributes for the `<select>` element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied to the `<select>` element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Get the currently selected option value.
	 *
	 * This method returns the value of the currently selected option in the select element.
	 *
	 * @since 0.1.0
	 * @return string|null The value of the currently selected option, or null if no option is selected.
	 */
	public function getSelectedOption(): ?string {
		return $this->selectedOption;
	}

	/**
	 * Check if the select element is disabled.
	 *
	 * This method returns a boolean value indicating whether the select element is disabled.
	 * If true, the `disabled` attribute is present on the `<select>` element.
	 *
	 * @since 0.1.0
	 * @return bool True if the select element is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Set the Selects HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the Select element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set one or more options for the select element.
	 *
	 * This method allows one or more options to be added to the select dropdown.
	 * Each option can be provided as a simple key-value pair, or as an array with `value`, `text`,
	 * and `selected` keys for more complex options.
	 *
	 * Example usage:
	 *
	 *     // Using key-value pairs:
	 *     $select->setOptions([
	 *         'value1' => 'Label 1',
	 *         'value2' => 'Label 2'
	 *     ]);
	 *
	 *     // Using an array for more complex options:
	 *     $select->setOptions([
	 *         ['value' => 'value1', 'text' => 'Label 1', 'selected' => true],
	 *         ['value' => 'value2', 'text' => 'Label 2']
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $options An array of options, either as key-value pairs or
	 *                       arrays with `value`, `text`, and `selected` keys.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setOptions( array $options ): self {
		$this->options = array_merge( $this->options, $options );

		return $this;
	}

	/**
	 * Set the optGroups for the select element.
	 *
	 * This method allows options to be grouped under labels in the select dropdown.
	 * Each optGroup can contain options that are either key-value pairs or arrays with `value`,
	 * `text`, and `selected` keys for more complex options.
	 *
	 * Example usage:
	 *
	 *     $select->setOptGroups([
	 *         'Group 1' => [
	 *             'value1' => 'Option 1',
	 *             ['value' => 'value2', 'text' => 'Option 2', 'selected' => true]
	 *         ],
	 *         'Group 2' => [
	 *             'value3' => 'Option 3',
	 *             'value4' => 'Option 4'
	 *         ]
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $optGroups An associative array of optGroups where keys are labels and values are arrays of options.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setOptGroups( array $optGroups ): self {
		$this->optGroups = array_merge( $this->optGroups, $optGroups );
		return $this;
	}

	/**
	 * Set additional HTML attributes for the `<select>` element.
	 *
	 * This method allows custom HTML attributes to be added to the `<select>` element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used
	 * to enhance accessibility or integrate with JavaScript.
	 *
	 * Example usage:
	 *
	 *     $select->setAttributes([
	 *         'id' => 'select-example',
	 *         'data-category' => 'selection',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set whether the select element should be disabled.
	 *
	 * This method disables the select element, preventing user interaction.
	 * When called with `true`, the `disabled` attribute is added to the `<select>` element.
	 *
	 * Example usage:
	 *
	 *     $select->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the select element should be disabled.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set the selected option for the select element.
	 *
	 * This method specifies which option should be selected by default when the select element is rendered.
	 *
	 * @since 0.1.0
	 * @param string|null $value The value of the option to be selected, or null to unset the selection.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setSelectedOption( ?string $value ): self {
		$this->selectedOption = $value;

		return $this;
	}
}
