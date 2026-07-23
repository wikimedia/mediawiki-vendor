<?php

namespace WebVTT\DOM\Internal;

/**
 * Sanitizes free-text WebVTT field values for output.
 *
 * Several block types (cue text, cue/region identifiers, NOTE and STYLE
 * content) embed author-supplied text directly into the file's line
 * structure. The WebVTT grammar restricts what that text may contain
 * (no "-->", no blank/leading/trailing line breaks depending on the
 * field), and violating those restrictions doesn't just produce
 * non-conformant output — it can cause a compliant parser to end the
 * block early and silently drop the rest of the content. These helpers
 * apply the same lossy-but-safe sanitization consistently wherever
 * that risk exists.
 */
final class VttTextSanitizer {

	/**
	 * Sanitizes a single-line field (e.g. a cue or region identifier).
	 *
	 * Line breaks are replaced with a space, since such fields must not
	 * span multiple lines, and any embedded "-->" is escaped so it can't
	 * be mistaken for the start of a timing line.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function sanitizeLine( string $value ): string {
		$value = preg_replace( '/\r\n|\r|\n/', ' ', $value );
		return str_replace( '-->', '--\>', $value );
	}

	/**
	 * Sanitizes a multi-line block field (e.g. cue text, NOTE, or STYLE content).
	 *
	 * Leading/trailing line breaks are trimmed, since they'd otherwise
	 * form a blank line against the block's header or terminator and end
	 * it immediately; any remaining internal run of line breaks is
	 * collapsed to a single one; and any embedded "-->" is escaped.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function sanitizeBlock( string $value ): string {
		$value = trim( $value, "\r\n" );
		$value = preg_replace( '/(?:\r\n|\r|\n){2,}/', "\n", $value );
		return str_replace( '-->', '--\>', $value );
	}
}
