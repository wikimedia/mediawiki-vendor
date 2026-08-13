<?php
declare( strict_types = 1 );

/**
 * Thumbnail.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Thumbnail` class, responsible for managing
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
use Wikimedia\Codex\Renderer\ThumbnailRenderer;

/**
 * Thumbnail
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Thumbnail extends Component {
	private string $id = '';

	public function __construct(
		ThumbnailRenderer $renderer,
		private ?string $backgroundImage,
		private ?string $placeholderClass,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the HTML ID for the thumbnail.
	 *
	 * This method returns the HTML `id` attribute value for the thumbnail element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the thumbnail.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the background image URL for the thumbnail.
	 *
	 * This method returns the URL of the background image that will be displayed within the thumbnail.
	 * The image serves as a visual preview of the content.
	 *
	 * @since 0.1.0
	 * @return ?string The URL of the background image.
	 */
	public function getBackgroundImage(): ?string {
		return $this->backgroundImage;
	}

	/**
	 * Get the CSS class for the custom placeholder icon.
	 *
	 * This method returns the CSS class for the custom placeholder icon that will be displayed if
	 * the background image is not provided.
	 * The placeholder gives users a visual indication of where an image will appear.
	 *
	 * @since 0.1.0
	 * @return ?string The CSS class for the placeholder icon.
	 */
	public function getPlaceholderClass(): ?string {
		return $this->placeholderClass;
	}

	/**
	 * Retrieve additional HTML attributes for the thumbnail element.
	 *
	 * This method returns an associative array of custom HTML attributes that will be applied to the outer
	 * `<span>` element of the thumbnail. These attributes can be used to improve accessibility, enhance styling,
	 * or integrate with JavaScript functionality.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the Thumbnail HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the Thumbnail element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the background image for the thumbnail.
	 *
	 * This method specifies the URL of the background image that will be displayed
	 * within the thumbnail. The image serves as a visual preview of the content.
	 *
	 * Example usage:
	 *
	 *     $thumbnail->setBackgroundImage('https://example.com/image.jpg');
	 *
	 * @since 0.1.0
	 * @param string $backgroundImage The URL of the background image.
	 * @return $this Returns the Thumbnail instance for method chaining.
	 */
	public function setBackgroundImage( string $backgroundImage ): self {
		$this->backgroundImage = $backgroundImage;

		return $this;
	}

	/**
	 * Set the CSS class for a custom placeholder icon.
	 *
	 * This method specifies a custom CSS class for a placeholder icon that will be displayed
	 * if the background image is not provided. The placeholder gives users a visual indication of where
	 * an image will appear.
	 *
	 * Example usage:
	 *
	 *     $thumbnail->setPlaceholderClass('custom-placeholder-icon');
	 *
	 * @since 0.1.0
	 * @param string $placeholderClass The CSS class for the placeholder icon.
	 * @return $this Returns the Thumbnail instance for method chaining.
	 */
	public function setPlaceholderClass( string $placeholderClass ): self {
		$this->placeholderClass = $placeholderClass;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the thumbnail element.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<span>` element of the thumbnail,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * Example usage:
	 *
	 *     $thumbnail->setAttributes([
	 *         'id' => 'thumbnail-id',
	 *         'data-category' => 'images',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Thumbnail instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
