<?php

namespace Wikimedia\Zest;

use Wikimedia\Dodo\Node;
use Wikimedia\Dodo\NodeList;
use Wikimedia\IDLeDOM\ParentNode;

/**
 * Zest.php (https://github.com/wikimedia/zest.php)
 * Copyright (c) 2019, C. Scott Ananian. (MIT licensed)
 * PHP port based on:
 *
 * Zest (https://github.com/chjj/zest)
 * A css selector engine.
 * Copyright (c) 2011-2012, Christopher Jeffrey. (MIT Licensed)
 * Domino version based on Zest v0.1.3 with bugfixes applied.
 */

class Zest {
	/**
	 * Find elements matching a CSS selector underneath $context.
	 * @param string $sel The CSS selector string
	 * @param ParentNode $context The scope for the search
	 * @return array Elements matching the CSS selector
	 */
	public static function find( string $sel, $context ): array {
	}

	/**
	 * Determine whether an element matches the given selector.
	 * @param Node $el The element to be tested
	 * @param string $sel The CSS selector string
	 * @return bool True iff the element matches the selector
	 */
	public static function matches( $el, string $sel ): bool {
	}

	/**
	 * Get descendants by ID.
	 * The PHP DOM doesn't provide this method for DOMElement, and the
	 * implementation in DOMDocument is broken.
	 *
	 * @param ParentNode $context
	 * @param string $id
	 * @return Element[] A list of the elements with the given ID. When there are more
	 *   than one, this method might return all of them or only the first one.
	 */
	public static function getElementsById( $context, string $id ): array {
	}

	/**
	 * Get descendants by tag name.
	 * The PHP DOM doesn't provide this method for DOMElement, and the
	 * implementation in DOMDocument has performance issues.
	 *
	 * @param ParentNode $context
	 * @param string $tagName
	 * @return NodeList
	 */
	public static function getElementsByTagName( $context, string $tagName ) {
	}

}
