<?php

declare( strict_types = 1 );

namespace Wikimedia\Dodo;

use Wikimedia\Dodo\Internal\FakeElement;
use Wikimedia\Dodo\Internal\FilteredElementList;
use Wikimedia\Dodo\Internal\NamespacePrefixMap;
use Wikimedia\Dodo\Internal\UnimplementedTrait;

/**
 * DocumentFragment
 */
class DocumentFragment extends ContainerNode implements \Wikimedia\IDLeDOM\DocumentFragment {
	// DOM mixins
	use NonElementParentNode;
	use ParentNode;

	// Stub out methods not yet implemented.
	use \Wikimedia\IDLeDOM\Stub\DocumentFragment;
	use UnimplementedTrait;

	// Helper functions from IDLeDOM
	use \Wikimedia\IDLeDOM\Helper\DocumentFragment;

	/**
	 * @param string $name
	 * @return mixed
	 */
	protected function _getMissingProp( string $name ) {
		switch ( $name ) {
			case 'attributes':
				// HACK! For compatibilty with W3C test suite, which
				// assumes that an access to 'attributes' will return
				// null.
				return null;
			case 'innerHTML':
				return $this->getInnerHTML(); // nonstandard but handy
			case 'outerHTML':
				return $this->getOuterHTML();  // nonstandard but handy
			default:
				return parent::_getMissingProp( $name );
		}
	}

	/** @inheritDoc */
	public function __construct( Document $nodeDocument ) {
		parent::__construct( $nodeDocument );
	}

	/**
	 * @inheritDoc
	 */
	final public function getNodeType(): int {
		return Node::DOCUMENT_FRAGMENT_NODE;
	}

	/**
	 * @inheritDoc
	 */
	final public function getNodeName(): string {
		return "#document-fragment";
	}

	/** @return DocumentFragment */
	protected function _subclassCloneNodeShallow(): Node {
		return new DocumentFragment( $this->_nodeDocument );
	}

	/** @inheritDoc */
	protected function _subclassIsEqualNode( Node $node ): bool {
		// Any two document fragments are shallowly equal.
		// Node.isEqualNode() will test their children for equality
		return true;
	}

	/** @inheritDoc */
	public function _xmlSerialize(
		?string $namespace, NamespacePrefixMap $prefixMap, int &$prefixIndex,
		bool $requireWellFormed, array &$markup
	): void {
		for ( $child = $this->getFirstChild(); $child !== null; $child = $child->getNextSibling() ) {
			$child->_xmlSerialize(
				$namespace, $prefixMap, $prefixIndex, $requireWellFormed,
				$markup
			);
		}
	}

	/** @inheritDoc */
	public function querySelectorAll( string $selectors ) {
		return $this->_fakeElement()->querySelectorAll( $selectors );
	}

	/** @inheritDoc */
	public function querySelector( string $selectors ) {
		return $this->_fakeElement()->querySelector( $selectors );
	}

	/** @inheritDoc */
	public function getElementById( string $id ) {
		$nl = new FilteredElementList( $this->_fakeElement(), static function ( $el ) use ( $id ) {
			return $el->getAttribute( 'id' ) === $id;
		} );
		return $nl->getLength() > 0 ? $nl->item( 0 ) : null;
	}

	/**
	 * Create a FakeElement so that we can invoke methods of Element on
	 * DocumentFragment "as if it were an element".
	 * @return FakeElement
	 */
	private function _fakeElement(): FakeElement {
		return new FakeElement( $this->_nodeDocument, function () {
			return $this->getFirstChild();
		} );
	}

	// Non-standard, but useful (github issue #73)

	/** @return string the inner HTML of this DocumentFragment */
	public function getInnerHTML(): string {
		return $this->_fakeElement()->getInnerHTML();
	}

	/** @return string the outer HTML of this DocumentFragment */
	public function getOuterHTML(): string {
		return $this->getInnerHTML();
	}
}
