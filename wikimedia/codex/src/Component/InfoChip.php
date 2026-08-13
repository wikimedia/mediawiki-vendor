<?php
declare( strict_types = 1 );

/**
 * InfoChip.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `InfoChip` class, responsible for managing
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
use Wikimedia\Codex\Renderer\InfoChipRenderer;

/**
 * InfoChip
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class InfoChip extends Component {
	private string $id = '';
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
		InfoChipRenderer $renderer,
		private string|HtmlSnippet $text,
		private string $status,
		private ?string $icon,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the InfoChip's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the InfoChip element.
	 * The ID can be used for targeting the chip with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the InfoChip element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the text content of the info chip.
	 *
	 * This method returns the text displayed inside the info chip.
	 * The text provides the primary information that the chip conveys.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The text content of the info chip.
	 */
	public function getText(): string|HtmlSnippet {
		return $this->text;
	}

	/**
	 * Get the status type of the info chip.
	 *
	 * This method returns the status type of the info chip, which determines its visual style.
	 * The status can be one of the following: 'notice', 'warning', 'error', or 'success'.
	 *
	 * @since 0.1.0
	 * @return string The status type of the info chip.
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * Get the custom icon class for the info chip.
	 *
	 * This method returns the CSS class for a custom icon used in the info chip, if applicable.
	 * This option is only available for chips with the "notice" status.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the custom icon, or null if no icon is set.
	 */
	public function getIcon(): ?string {
		return $this->icon;
	}

	/**
	 * Retrieve additional HTML attributes for the outer `<div>` element.
	 *
	 * This method returns an associative array of additional HTML attributes that will be applied
	 * to the outer `<div>` element of the info chip. These attributes can be used to improve
	 * accessibility, customization, or to integrate with JavaScript.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the InfoChip's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the InfoChip element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the text content for the info chip.
	 *
	 * This method specifies the text that will be displayed inside the info chip.
	 * The text provides the primary information that the chip conveys.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $text The text to be displayed inside the info chip.
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setText( string|HtmlSnippet $text ): self {
		$this->text = $text;

		return $this;
	}

	/**
	 * Set the status type for the info chip.
	 *
	 * This method sets the visual style of the info chip based on its status.
	 * The status can be one of the following:
	 * - 'notice': For general information.
	 * - 'warning': For cautionary information.
	 * - 'error': For error messages.
	 * - 'success': For success messages.
	 *
	 * The status type is applied as a CSS class (`cdx-info-chip--{status}`) to the chip element.
	 *
	 * @since 0.1.0
	 * @param string $status The status type (e.g., 'notice', 'warning', 'error', 'success').
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setStatus( string $status ): self {
		if ( !in_array( $status, self::ALLOWED_STATUS_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid status: $status" );
		}
		$this->status = $status;

		return $this;
	}

	/**
	 * Set a custom icon for the "notice" status chip.
	 *
	 * This method specifies a CSS class for a custom icon to be displayed inside the chip.
	 * This option is applicable only for chips with the "notice" status.
	 * Chips with other status types (warning, error, success) do not support custom icons and will ignore this setting.
	 *
	 * @since 0.1.0
	 * @param string|null $icon The CSS class for the custom icon, or null to remove the icon.
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setIcon( ?string $icon ): self {
		if ( $this->status === 'notice' && ( $icon !== null && trim( $icon ) === '' ) ) {
			throw new InvalidArgumentException( 'Custom icons are only allowed for "notice" status.' );
		}
		$this->icon = $icon;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer `<div>` element.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<div>` element of the info chip,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $infoChip->setAttributes([
	 *         'id' => 'info-chip-example',
	 *         'data-category' => 'info',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
