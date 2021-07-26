<?php

namespace RemexHtml\DOM;

use RemexHtml\HTMLData;
use RemexHtml\Tokenizer\Attributes;
use RemexHtml\TreeBuilder\Element;
use RemexHtml\TreeBuilder\TreeBuilder;
use RemexHtml\TreeBuilder\TreeHandler;

use Wikimedia\Dodo\Document as DOMDocument;
use Wikimedia\Dodo\Node as DOMNode;

/**
 * A TreeHandler which constructs a DOMDocument.
 *
 * Note that this class permits third-party `DOMImplementation`s
 * (documents other than `\DOMDocument`, nodes other than `\DOMNode`,
 * etc) and so no enforced PHP type hints are used which name these
 * classes directly.  For the sake of static type checking, the
 * types *in comments* are given as if the standard PHP `\DOM*`
 * classes are being used but at runtime everything is duck-typed.
 */
class DOMBuilder implements TreeHandler {

	/**
	 * @param array $options An associative array of options:
	 *   - errorCallback : A function which is called on parse errors
	 *   - suppressHtmlNamespace : omit the namespace when creating HTML
	 *     elements. False by default.
	 *   - suppressIdAttribute : don't call the nonstandard
	 *     DOMElement::setIdAttribute() method while constructing elements.
	 *     False by default (this method is needed for efficient
	 *     DOMDocument::getElementById() calls).  Set to true if you are
	 *     using a W3C spec-compliant DOMImplementation and wish to avoid
	 *     nonstandard calls.
	 *   - domImplementation: The DOMImplementation object to use.  If this
	 *     parameter is missing or null, a new DOMImplementation object will
	 *     be constructed using the `domImplementationClass` option value.
	 *     You can use a third-party DOM implementation by passing in an
	 *     appropriately duck-typed object here.
	 *   - domImplementationClass: The string name of the DOMImplementation
	 *     class to use.  Defaults to `\DOMImplementation::class` but
	 *     you can use a third-party DOM implementation by passing
	 *     an alternative class name here.
	 *   - domExceptionClass: The string name of the DOMException
	 *     class to use.  Defaults to `\DOMException::class` but
	 *     you can use a third-party DOM implementation by passing
	 *     an alternative class name here.
	 */
	public function __construct( $options = [] ) {
	}
	/**
	 * Get the constructed document or document fragment. In the fragment case,
	 * a DOMElement is returned, and the caller is expected to extract its
	 * inner contents, ignoring the wrapping element. This convention is
	 * convenient because the wrapping element gives libxml somewhere to put
	 * its namespace declarations. If we copied the children into a
	 * DOMDocumentFragment, libxml would invent new prefixes for the orphaned
	 * namespaces.
	 *
	 * @return DOMNode
	 */
	public function getFragment() {
	}

	/**
	 * Returns true if the document was coerced due to libxml limitations. We
	 * follow HTML 5.1 § 8.2.7 "Coercing an HTML DOM into an infoset".
	 *
	 * @return bool
	 */
	public function isCoerced() {
	}

	public function startDocument( $fragmentNamespace, $fragmentName ) {
	}

	/**
	 * @param string|null $doctypeName
	 * @param string|null $public
	 * @param string|null $system
	 * @return DOMDocument
	 */
	protected function createDocument(
		string $doctypeName = null,
		string $public = null,
		string $system = null
	) {
	}

	public function endDocument( $pos ) {
	}

	public function characters( $preposition, $refElement, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
	}

	public function insertElement( $preposition, $refElement, Element $element, $void,
		$sourceStart, $sourceLength
	) {
	}

	public function endTag( Element $element, $sourceStart, $sourceLength ) {
	}

	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
	}

	public function comment( $preposition, $refElement, $text, $sourceStart, $sourceLength ) {
	}

	public function error( $text, $pos ) {
	}

	public function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
	}

	public function removeNode( Element $element, $sourceStart ) {
	}

	public function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
	}
}
