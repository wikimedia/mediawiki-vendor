<?php
declare( strict_types = 1 );

/**
 * Message.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Message` class, responsible for managing
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
use Wikimedia\Codex\Renderer\MessageRenderer;
use Wikimedia\Codex\Traits\ContentSetter;

/**
 * Message
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Message extends Component {
	use ContentSetter;

	private string $id = '';

	/**
	 * The valid status types for messages.
	 */
	public const STATUS_TYPES = [
		'notice',
		'warning',
		'error',
		'success',
	];

	public function __construct(
		MessageRenderer $renderer,
		private string|HtmlSnippet $content,
		private string $type,
		private bool $inline,
		private string|HtmlSnippet $heading,
		private string $iconClass,
		private array $attributes
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the Message's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the Message element.
	 * The ID can be used for targeting the message with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Message element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the content of the message box.
	 *
	 * This method returns the text or HTML content displayed inside the message box.
	 * The content provides the primary feedback or information that the message conveys to the user.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The content of the message box.
	 */
	public function getContent(): string|HtmlSnippet {
		return $this->content;
	}

	/**
	 * Get the type of the message box.
	 *
	 * This method returns the type of the message, which determines its visual style.
	 * The type can be one of the following: 'notice', 'warning', 'error', 'success'.
	 *
	 * @since 0.1.0
	 * @return string The type of the message box.
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * Check if the message box is displayed inline.
	 *
	 * This method returns a boolean indicating whether the message box is displayed inline,
	 * without additional padding, background color, or border.
	 *
	 * @since 0.1.0
	 * @return bool True if the message box is displayed inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get the heading of the message box.
	 *
	 * This method returns the heading text prominently displayed at the top of the message content.
	 * The heading helps to quickly convey the primary purpose or topic of the message.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The heading text of the message box.
	 */
	public function getHeading(): string|HtmlSnippet {
		return $this->heading;
	}

	/**
	 * Get the CSS class name for the icon.
	 *
	 * This method returns the CSS class name for the icon displayed in the message box,
	 * enhancing the visual representation of the message.
	 *
	 * @since 0.1.0
	 * @return string The CSS class name for the icon.
	 */
	public function getIconClass(): string {
		return $this->iconClass;
	}

	/**
	 * Get the additional HTML attributes for the message box.
	 *
	 * This method returns an associative array of additional HTML attributes that are applied
	 * to the outer `<div>` element of the message box. These attributes can be used to enhance
	 * accessibility or integrate with JavaScript.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the Message's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the Message element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the content of the message.
	 *
	 * @param string|HtmlSnippet $content Text or HTML to be displayed inside the message box.
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setContent( string|HtmlSnippet $content ): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * Set the type of the message box.
	 *
	 * This method sets the visual style of the message box based on its type.
	 * The type can be one of the following:
	 * - 'notice': For general information.
	 * - 'warning': For cautionary information.
	 * - 'error': For error messages.
	 * - 'success': For success messages.
	 *
	 * The type is applied as a CSS class (`cdx-message--{type}`) to the message element.
	 *
	 * @since 0.1.0
	 * @param string $type The type of message (e.g., 'notice', 'warning', 'error', 'success').
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setType( string $type ): self {
		if ( !in_array( $type, self::STATUS_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid message type: $type" );
		}
		$this->type = $type;

		return $this;
	}

	/**
	 * Set the inline display of the message box.
	 *
	 * This method determines whether the message box should be displayed inline,
	 * without padding, background color, or border. Inline messages are typically used for
	 * validation feedback or brief notifications within the flow of content.
	 *
	 * @since 0.1.0
	 * @param bool $inline Whether the message box should be displayed inline.
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set the heading of the message box.
	 *
	 * This method sets a heading for the message box, which will be displayed prominently at the top of the message
	 * content. The heading helps to quickly convey the primary purpose or topic of the message.
	 *
	 * Example usage:
	 *
	 *     $message->setHeading('Error: Invalid Input');
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $heading The heading text to be displayed inside the message box.
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setHeading( string|HtmlSnippet $heading ): self {
		$this->heading = $heading;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the message box.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<div>` element of the message box,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $message->setAttributes([
	 *         'id' => 'error-message',
	 *         'data-type' => 'error',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
