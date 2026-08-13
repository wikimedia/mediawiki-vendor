<?php
declare( strict_types = 1 );

/**
 * Sanitizer.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Sanitizer` class, which is responsible
 * for sanitizing data before rendering. The Sanitizer ensures that all output is safe
 * and helps prevent XSS and other security vulnerabilities.
 *
 * The Sanitizer class includes methods for sanitizing text, HTML content, and HTML attributes.
 * By centralizing the sanitization logic, it adheres to the Single Responsibility Principle
 * and enhances the maintainability and security of the codebase.
 *
 * @category Utility
 * @package  Codex\Utility
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Utility;

use Wikimedia\Codex\Component\HtmlSnippet;

/**
 * Sanitizer is a class responsible for sanitizing data before rendering.
 *
 * This class provides methods to sanitize text, HTML content, and attributes.
 * It ensures that all data outputted to the user is properly sanitized, preventing XSS
 * and other injection attacks.
 *
 * @category Utility
 * @package  Codex\Utility
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Sanitizer {

	/**
	 * Sanitize a plain text string.
	 *
	 * This method escapes special HTML characters in a string to prevent XSS attacks.
	 * It should be used when the content does not contain any HTML markup and needs
	 * to be treated strictly as text.
	 *
	 * @since 0.1.0
	 * @param string|HtmlSnippet|null $textOrSnippet The plain text to sanitize.
	 * @return string The sanitized text.
	 */
	public function sanitizeText( $textOrSnippet ): string {
		if ( $textOrSnippet instanceof HtmlSnippet ) {
			return $textOrSnippet->getContent();
		}
		return htmlspecialchars( $textOrSnippet ?? '', ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Resolves an associative array of HTML attributes into a string for an HTML tag.
	 * Boolean attributes (like `disabled`) are rendered without a value.
	 * Array-based attributes (like `class`) are concatenated into a single string, separated by
	 * spaces. This method also handles escaping.
	 *
	 * @since 0.1.0
	 * @param array $attributes Key-value pairs of HTML attributes.
	 * @return string The attributes as a string, ready to be included in an HTML tag.
	 */
	public function resolveAttributes( array $attributes ): string {
		$resolvedAttributes = [];

		foreach ( $attributes as $key => $value ) {
			$escKey = $this->sanitizeText( $key );

			// If the value is true, include the key as an attribute without a value.
			if ( $value === true ) {
				$resolvedAttributes[] = $escKey;
			} else {
				$escValue = $this->sanitizeAttributeValue( $value );
				$resolvedAttributes[] = "$escKey=\"$escValue\"";
			}
		}

		return implode( ' ', $resolvedAttributes );
	}

	/**
	 * Sanitize a single attribute value. Most code should not use this, but should use
	 * resolveAttributes() instead.
	 * @param string|string[] $attrValue Plain text attribute value, or array of plain text values
	 * @return string Escaped attribute value, safe for use in an HTML attribute string.
	 *   This does NOT include the attribute name, or quotes.
	 */
	public function sanitizeAttributeValue( string|array $attrValue ): string {
		if ( is_array( $attrValue ) ) {
			$attrValue = implode( ' ', $attrValue );
		}
		return $this->sanitizeText( $attrValue );
	}

	/**
	 * Sanitize a URL.
	 *
	 * This method ensures the URL is safe by validating it, removing illegal characters,
	 * and ensuring it uses an allowed scheme. This function does not escape it for HTML output,
	 * to do that either use sanitizeText() or use Mustache's built-in escaping with `{{ url }}`.
	 *
	 * @since 0.1.0
	 * @param string|null $url The URL to sanitize.
	 * @return string The sanitized URL.
	 */
	public function sanitizeUrl( ?string $url ): string {
		if ( $url === null || $url === '' ) {
			return '';
		}

		$sanitizedUrl = filter_var( $url, FILTER_SANITIZE_URL );

		if ( !filter_var( $sanitizedUrl, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		$parsedUrl = parse_url( $sanitizedUrl );

		$allowedSchemes = [ 'http', 'https' ];
		if (
			!isset( $parsedUrl['scheme'] ) ||
			!in_array( strtolower( $parsedUrl['scheme'] ), $allowedSchemes, true )
		) {
			return '';
		}

		return $this->unparseUrl( $parsedUrl );
	}

	/**
	 * Helper function to rebuild a URL from its parsed components.
	 *
	 * @since 0.1.0
	 * @param array $parsedUrl The parsed URL components.
	 * @return string The reconstructed URL.
	 */
	private function unparseUrl( array $parsedUrl ): string {
		$scheme   = isset( $parsedUrl['scheme'] ) ? $parsedUrl['scheme'] . '://' : '';
		$host     = $parsedUrl['host'] ?? '';
		$port     = isset( $parsedUrl['port'] ) ? ':' . $parsedUrl['port'] : '';
		$user     = $parsedUrl['user'] ?? '';
		$pass     = isset( $parsedUrl['pass'] ) ? ':' . $parsedUrl['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = $parsedUrl['path'] ?? '';
		$query    = isset( $parsedUrl['query'] ) ? '?' . $parsedUrl['query'] : '';
		$fragment = isset( $parsedUrl['fragment'] ) ? '#' . $parsedUrl['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}
}
