<?php
declare( strict_types = 1 );

/**
 * Card.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Card` class, responsible for managing
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
use Wikimedia\Codex\Renderer\CardRenderer;

/**
 * Card
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Card extends Component {
	private string $id = '';

	public function __construct(
		CardRenderer $renderer,
		private string|HtmlSnippet $title,
		private string|HtmlSnippet $description,
		private string|HtmlSnippet $supportingText,
		private string $url,
		private ?string $iconClass,
		private ?Thumbnail $thumbnail,
		private array $attributes,
	) {
		parent::__construct( $renderer );
	}

	/**
	 * Get the card's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the card element. The ID is useful for targeting
	 * the card with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the card element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the title text displayed on the card.
	 *
	 * This method returns the title text prominently displayed on the card.
	 * The title usually represents the main topic or subject of the card.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The title of the card.
	 */
	public function getTitle(): string|HtmlSnippet {
		return $this->title;
	}

	/**
	 * Get the description text displayed on the card.
	 *
	 * This method returns the description text that provides additional details about
	 * the card's content. The description is typically rendered below the title.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The description of the card.
	 */
	public function getDescription(): string|HtmlSnippet {
		return $this->description;
	}

	/**
	 * Get the supporting text displayed on the card.
	 *
	 * This method returns the supporting text that provides further context or details
	 * about the card's content. The supporting text is typically rendered at the bottom
	 * of the card, below the title and description.
	 *
	 * @since 0.1.0
	 * @return string|HtmlSnippet The supporting text of the card.
	 */
	public function getSupportingText(): string|HtmlSnippet {
		return $this->supportingText;
	}

	/**
	 * Get the URL the card links to.
	 *
	 * This method returns the URL that the card links to if the card is clickable.
	 * If a URL is provided, the card is rendered as an anchor (`<a>`) element.
	 *
	 * @since 0.1.0
	 * @return string The URL the card links to.
	 */
	public function getUrl(): string {
		return $this->url;
	}

	/**
	 * Get the icon class for the card.
	 *
	 * This method returns the CSS class used for the icon displayed inside the card.
	 * The icon is an optional visual element that can enhance the card's content.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the icon, or null if no icon is set.
	 */
	public function getIconClass(): ?string {
		return $this->iconClass;
	}

	/**
	 * Get the thumbnail object associated with the card.
	 *
	 * This method returns the Thumbnail object representing the card's thumbnail.
	 *
	 * @since 0.1.0
	 * @return Thumbnail|null The Thumbnail object or null if no thumbnail is set.
	 */
	public function getThumbnail(): ?Thumbnail {
		return $this->thumbnail;
	}

	/**
	 * Retrieve additional HTML attributes for the card element.
	 *
	 * This method returns an associative array of additional HTML attributes that will be applied
	 * to the card element. These attributes can be used to enhance the appearance, accessibility,
	 * or functionality of the card.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Set the card's HTML ID attribute.
	 *
	 * @deprecated Use setAttributes() to set the ID
	 * @since 0.1.0
	 * @param string $id The ID for the card element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the title for the card.
	 *
	 * The title is the primary text displayed on the card, typically representing the main topic
	 * or subject of the card. It is usually rendered in a larger font and is the most prominent
	 * piece of text on the card.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $title The title text displayed on the card.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setTitle( string|HtmlSnippet $title ): self {
		if ( trim( $title ) === '' ) {
			throw new InvalidArgumentException( 'Card title cannot be empty.' );
		}
		$this->title = $title;

		return $this;
	}

	/**
	 * Set the description for the card.
	 *
	 * The description provides additional details about the card's content. It is typically rendered
	 * below the title in a smaller font. The description is optional and can be used to give users
	 * more context about what the card represents.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $description The description text displayed on the card.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setDescription( string|HtmlSnippet $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set the supporting text for the card.
	 *
	 * The supporting text is an optional piece of text that can provide additional information
	 * or context about the card. It is typically placed at the bottom of the card, below the
	 * title and description, in a smaller font. This text can be used for subtitles, additional
	 * notes, or other relevant details.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet $supportingText The supporting text displayed on the card.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setSupportingText( string|HtmlSnippet $supportingText ): self {
		$this->supportingText = $supportingText;

		return $this;
	}

	/**
	 * Set the URL for the card. If provided, the card will be an `<a>` element.
	 *
	 * This method makes the entire card clickable by wrapping it in an anchor (`<a>`) element,
	 * turning it into a link. This is particularly useful for cards that serve as navigational
	 * elements, leading users to related content, such as articles, profiles, or external pages.
	 *
	 * @since 0.1.0
	 * @param string $url The URL the card should link to.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setUrl( string $url ): self {
		if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new InvalidArgumentException( "Invalid URL: $url" );
		}
		$this->url = $url;

		return $this;
	}

	/**
	 * Set the icon class for the card.
	 *
	 * This method specifies a CSS class for an icon to be displayed inside the card.
	 * The icon can be used to visually represent the content or purpose of the card.
	 * It is typically rendered at the top or side of the card, depending on the design.
	 *
	 * @since 0.1.0
	 * @param string $iconClass The CSS class for the icon.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setIconClass( string $iconClass ): self {
		$this->iconClass = $iconClass;

		return $this;
	}

	/**
	 * Set the thumbnail for the card.
	 *
	 * This method accepts a `Thumbnail` object, which configures the thumbnail associated with the card.
	 *
	 * Example usage:
	 *     $thumbnail = Thumbnail::setBackgroundImage('https://example.com/image.jpg');
	 *     $card->setThumbnail($thumbnail);
	 *
	 * @since 0.1.0
	 * @param Thumbnail $thumbnail The Thumbnail object.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setThumbnail( Thumbnail $thumbnail ): self {
		$this->thumbnail = $thumbnail;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the card element.
	 *
	 * This method allows custom HTML attributes to be added to the card element, such as `id`, `data-*`, `aria-*`,
	 * or any other valid attributes. These attributes can be used to integrate the card with JavaScript, enhance
	 * accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}
}
