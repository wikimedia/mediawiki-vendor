<?php
declare( strict_types = 1 );

/**
 * Tab.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Tab` class, responsible for managing
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
use Wikimedia\Codex\Traits\ContentSetter;

/**
 * Tab
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Tab {
	use ContentSetter;

	private string $id = '';

	public function __construct(
		private string $name,
		private string $label,
		private string|HtmlSnippet $content,
		private bool $selected,
		private bool $disabled
	) {
	}

	/**
	 * Get the Tab's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the tab element, which is used
	 * for identifying the tab in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Tab element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the tab's name.
	 *
	 * @since 0.1.0
	 * @return string The unique name of the tab.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the tab's label.
	 *
	 * @since 0.1.0
	 * @return string The label of the tab.
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Get the tab's content.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The content of the tab.
	 */
	public function getContent(): string|HtmlSnippet {
		return $this->content;
	}

	/**
	 * Get the tab's selected state.
	 *
	 * @since 0.1.0
	 * @return bool Whether the tab is selected.
	 */
	public function isSelected(): bool {
		return $this->selected;
	}

	/**
	 * Get the tab's disabled state.
	 *
	 * @since 0.1.0
	 * @return bool Whether the tab is disabled.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Set the tab HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the tab element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the name for the tab.
	 *
	 * The name is used for programmatic selection, and it also serves as the default label if none is provided.
	 *
	 * @since 0.1.0
	 * @param string $name The unique name of the tab, used for programmatic selection.
	 * @return $this Returns the Tab instance for method chaining.
	 */
	public function setName( string $name ): self {
		if ( trim( $name ) === '' ) {
			throw new InvalidArgumentException( 'Tab name cannot be empty.' );
		}
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the label for the tab.
	 *
	 * The label corresponds to the text displayed in the Tabs component's header for this tab.
	 * If not set, the label will default to the name of the tab.
	 *
	 * @since 0.1.0
	 * @param string $label The label text to be displayed in the Tabs component's header.
	 * @return $this Returns the Tab instance for method chaining.
	 */
	public function setLabel( string $label ): self {
		if ( trim( $label ) === '' ) {
			throw new InvalidArgumentException( 'Tab label cannot be empty.' );
		}
		$this->label = $label;

		return $this;
	}

	/**
	 * Set whether the tab should be selected by default.
	 *
	 * @since 0.1.0
	 * @param bool $selected Whether this tab should be selected by default.
	 * @return $this Returns the Tab instance for method chaining.
	 */
	public function setSelected( bool $selected ): self {
		$this->selected = $selected;

		return $this;
	}

	/**
	 * Set the disabled state for the tab.
	 *
	 * Disabled tabs cannot be accessed via label clicks or keyboard navigation.
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether or not the tab is disabled.
	 * @return $this Returns the Tab instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set the content of the tab.
	 *
	 * @param string|HtmlSnippet $content Text or HTML to be displayed when this tab is selected.
	 * @return $this Returns the Tab instance for method chaining.
	 */
	public function setContent( string|HtmlSnippet $content ): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * Backwards compatibility for the pre-0.8 API.
	 * @return static
	 */
	public function build() {
		return $this;
	}
}
