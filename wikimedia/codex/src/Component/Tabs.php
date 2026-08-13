<?php
declare( strict_types = 1 );

/**
 * Tabs.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Tabs` class, responsible for managing
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
use Wikimedia\Codex\Renderer\TabsRenderer;

/**
 * Tabs
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Tabs extends Component {
	private string $id = '';

	public function __construct(
		TabsRenderer $renderer,
		private array $tabs,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the HTML ID for the tabs.
	 *
	 * This method returns the HTML `id` attribute value for the tabs element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the tabs.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the array of tabs in the component.
	 *
	 * This method returns an array of `Tab` objects that represent each tab in the component.
	 * Each tab contains properties such as label, content, selected state, and disabled state.
	 *
	 * @since 0.1.0
	 * @return array The array of `Tab` objects representing the tabs in the component.
	 */
	public function getTabs(): array {
		return $this->tabs;
	}

	/**
	 * Get the additional HTML attributes for the `<form>` element.
	 *
	 * This method returns an associative array of custom HTML attributes applied to the `<form>` element
	 * that wraps the tabs. These attributes can be used to enhance accessibility or integrate with JavaScript.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the Tabs HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the Tabs element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Add one or more tabs to the tabs component.
	 *
	 * This method allows the addition of one or more `Tab` objects to the tabs component.
	 * Each tab's properties, such as label, content, selected state, and disabled state,
	 * are used to construct and configure the tabs.
	 *
	 * Example usage:
	 *
	 *     $tabs->setTab($tab1)
	 *         ->setTab([$tab2, $tab3]);
	 *
	 * @since 0.1.0
	 * @param Tab|Tab[] $tab A `Tab` object or an array of `Tab` objects to add.
	 * @return $this Returns the Tabs instance for method chaining.
	 */
	public function setTab( Tab|array $tab ): self {
		if ( is_array( $tab ) ) {
			foreach ( $tab as $t ) {
				$this->tabs[] = $t;
			}
		} else {
			$this->tabs[] = $tab;
		}

		return $this;
	}

	/**
	 * Set additional HTML attributes for the `<form>` element.
	 *
	 * This method allows custom HTML attributes to be added to the `<form>` element that wraps the tabs,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used
	 * to enhance accessibility or integrate with JavaScript.
	 *
	 * Example usage:
	 *
	 *     $tabs->setAttributes([
	 *         'id' => 'tabs-form',
	 *         'data-category' => 'navigation',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Tabs instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
