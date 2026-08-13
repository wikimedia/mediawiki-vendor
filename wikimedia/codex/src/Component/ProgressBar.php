<?php
declare( strict_types = 1 );

/**
 * ProgressBar.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `ProgressBar` class, responsible for managing
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
use Wikimedia\Codex\Renderer\ProgressBarRenderer;

/**
 * ProgressBar
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class ProgressBar extends Component {
	private string $id = '';

	public function __construct(
		ProgressBarRenderer $renderer,
		private string $label,
		private bool $inline,
		private bool $disabled,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the ProgressBar's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the progress bar element, which is used
	 * for identifying the progress bar in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the ProgressBar.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the ARIA label for the progress bar.
	 *
	 * This method returns the ARIA label used for the progress bar, which is important for accessibility.
	 * The label provides a descriptive name for the progress bar, helping users with assistive technologies
	 * to understand its purpose.
	 *
	 * @since 0.1.0
	 * @return string The ARIA label for the progress bar.
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Get whether the progress bar is inline.
	 *
	 * This method returns a boolean value indicating whether the progress bar is the smaller, inline variant.
	 *
	 * @since 0.1.0
	 * @return bool True if the progress bar is inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get whether the progress bar is disabled.
	 *
	 * This method returns a boolean value indicating whether the progress bar is disabled.
	 *
	 * @since 0.1.0
	 * @return bool True if the progress bar is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Get the additional HTML attributes for the outer `<div>` element.
	 *
	 * This method returns an associative array of HTML attributes that are applied to the outer `<div>` element of the
	 * progress bar. These attributes can include `id`, `data-*`, `aria-*`, or any other valid HTML attributes.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the ProgressBar's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the ProgressBar element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the ARIA label for the progress bar.
	 *
	 * This method sets the ARIA label for the progress bar, which is important for accessibility.
	 * The label provides a descriptive name for the progress bar, helping users with assistive technologies
	 * to understand its purpose.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setLabel('File upload progress');
	 *
	 * @since 0.1.0
	 * @param string $label The ARIA label for the progress bar.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setLabel( string $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set whether the progress bar should be displayed inline.
	 *
	 * This method sets the `inline` property, which controls whether the progress bar should be
	 * displayed as a smaller, inline variant. The inline variant is typically used in compact spaces.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setInline(true);
	 *
	 * @since 0.1.0
	 * @param bool $inline Whether the progress bar should be displayed inline.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set whether the progress bar is disabled.
	 *
	 * This method sets the `disabled` property, which controls whether the progress bar is disabled.
	 * A disabled progress bar may be visually different and indicate to the user that it is inactive.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the progress bar is disabled.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer `<div>` element.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<div>` element of the progress bar,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setAttributes([
	 *         'id' => 'file-upload-progress',
	 *         'data-upload' => 'true',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
