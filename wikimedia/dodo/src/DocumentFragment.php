<?php

declare( strict_types = 1 );

namespace Wikimedia\Dodo;

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
	use \Wikimedia\IDLeDOM\Helper\DocumentFragment {
		__get as protected _getHelper;
	}

	/**
	 * HACK! For compatibilty with W3C test suite, which assumes that an
	 * access to 'attributes' will return null.
	 * @param string $name
	 * @return mixed
	 */
	public function __get( string $name ) {
		if ( $name === 'attributes' ) {
			return null;
		}
		return $this->_getHelper( $name );
	}

	/** @inheritDoc */
	public function __construct( Document $nodeDocument ) {
		parent::__construct( $nodeDocument );
	}

	/**
	 * @inheritDoc
	 */
	final public function getNodeType() : int {
		return Node::DOCUMENT_FRAGMENT_NODE;
	}

	/**
	 * @inheritDoc
	 */
	final public function getNodeName() : string {
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
	) : void {
		for ( $child = $this->getFirstChild(); $child !== null; $child = $child->getNextSibling() ) {
			$child->_xmlSerialize(
				$namespace, $prefixMap, $prefixIndex, $requireWellFormed,
				$markup
			);
		}
	}

	// Non-standard, but useful (github issue #73)

	/** @return string the inner HTML of this DocumentFragment */
	public function getInnerHTML() : string {
		$result = [];
		$this->_htmlSerialize( $result );
		return implode( '', $result );
	}

	/** @return string the outer HTML of this DocumentFragment */
	public function getOuterHTML(): string {
		return $this->getInnerHTML();
	}
}
