<?php
declare( strict_types = 1 );

/**
 * HtmlSnippet.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `HtmlSnippet` class, responsible for managing
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

/**
 * Wrapper class for raw HTML strings.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class HtmlSnippet {

	/**
	 * The safe HTML content to be rendered.
	 */
	private string $content;

	/**
	 * Constructor for the HtmlSnippet component.
	 *
	 * Initializes an instance of HtmlSnippet with the specified content and attributes.
	 *
	 * @param string $content The safe HTML content to be rendered.
	 * @param-taint $content exec_html Callers are responsible for escaping.
	 */
	public function __construct( string $content ) {
		$this->content = $content;
	}

	/**
	 * Get the raw HTML content.
	 *
	 * This method returns the raw HTML content for rendering.
	 *
	 * @since 0.1.0
	 * @return string The raw HTML content.
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * Backwards compatibility for the pre-0.8.0 API.
	 * @deprecated
	 * @param string $content
	 * @return HtmlSnippet
	 */
	public function setContent( string $content ): self {
		$this->content = $content;
		return $this;
	}

	/**
	 * Backwards compatibility for the pre-0.8.0 API.
	 * @deprecated
	 * @return HtmlSnippet
	 */
	public function build(): self {
		return $this;
	}
}
