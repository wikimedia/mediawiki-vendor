<?php
declare( strict_types = 1 );

namespace Wikimedia\Codex\Contract;

use Wikimedia\Codex\Utility\Sanitizer;

abstract class Renderer {

	public function __construct( protected Sanitizer $sanitizer ) {
	}

	/**
	 * Renders the HTML markup for a Component.
	 *
	 * This method is responsible for generating the complete HTML markup for the provided
	 * component object. The implementation should ensure that the generated markup is
	 * consistent with the Codex design system's guidelines.
	 *
	 * @since 0.1.0
	 * @param Component $component The Component object to render.
	 * @return string The generated HTML markup for the component.
	 */
	abstract public function render( Component $component ): string;

	/**
	 * Get extra classes to add to an existing class string. Should be used in templates like this:
	 *     class="cdx-foo cdx-foo--bar{{{extraClasses}}}"
	 *
	 * @param array $attributes
	 * @return string Escaped string suitable for inclusion in a class attribute value. Does not
	 *   include the attribute name or quotes. Starts with a space, unless it's empty.
	 */
	public function getExtraClasses( array $attributes ): string {
		$classes = $this->sanitizer->sanitizeAttributeValue( $attributes['class'] ?? '' );
		return $classes === '' ? '' : ' ' . $classes;
	}

	/**
	 * Get non-class attributes to add to an HTML tag. Should be used in templates like this:
	 *     <div class="..." {{{attributes}}}>
	 *
	 * @param array $attributes
	 * @param string[] $exclude Attribute names to exclude
	 * @return string Attribute string suitable for inclusion in an HTML tag. Includes attribute
	 *   names and quotes. Does not include the attributes in $exclude. Starts with a space, unless
	 *   it's empty.
	 */
	public function getOtherAttributes( array $attributes, array $exclude = [ 'class' ] ): string {
		$attrs = $this->sanitizer->resolveAttributes(
			array_diff_key( $attributes, array_flip( $exclude ) )
		);
		return $attrs === '' ? '' : ' ' . $attrs;
	}
}
