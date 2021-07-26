<?php

declare( strict_types = 1 );

namespace Wikimedia\Dodo\Internal;

use Wikimedia\Dodo\Node;

/*
 * DOM-LS specifies that in the
 * event that two Elements have
 * the same 'id' attribute value,
 * the first one, in document order,
 * shall be returned from getElementById.
 *
 * This data structure makes that
 * as performant as possible, by:
 *
 * 1. Caching the first element in the list, in document order
 * It is updated on move because a move is treated as a
 * removal followed by an insertion, and those two operations
 * will update this table.
 *
 * 2. Elements are looked up by an integer index set when they
 * are adopted by Document. This index gives a canonical
 * integer representation of an Element, so we can operate
 * on integers instead of Elements.
 */
class MultiId {
	/** @var Node[] */
	public $table = [];

	/** @var int */
	public $length = 0;

	/**
	 * The first element, in document order.
	 *
	 * null indicates the cache is not set and the first element must be re-computed.
	 *
	 * @var Node|null
	 */
	public $first = null;

	/**
	 * @param Node $node
	 */
	public function __construct( Node $node ) {
		$this->table[$node->_documentIndex] = $node;
		$this->length = 1;
		$this->first = null;
	}

	/**
	 * Add a Node to array in O(1) time by using Node::$_documentIndex
	 * as the array index.
	 *
	 * @param Node $node
	 */
	public function add( Node $node ) {
		if ( !isset( $this->table[$node->_documentIndex] ) ) {
			$this->table[$node->_documentIndex] = $node;
			$this->length++;
			$this->first = null; /* invalidate cache */
		}
	}

	/**
	 * Remove a Node from the array in O(1) time by using Node::$_documentIndex
	 * to perform the lookup.
	 *
	 * @param Node $node
	 */
	public function del( Node $node ) {
		if ( $this->table[$node->_documentIndex] ) {
			unset( $this->table[$node->_documentIndex] );
			$this->length--;
			$this->first = null; /* invalidate cache */
		}
	}

	/**
	 * Retreive that Node from the array which appears first in document order in
	 * the associated document.
	 *
	 * Cache the value for repeated lookups.
	 *
	 * The cache is invalidated each time the array is modified. The list
	 * is modified when a Node is inserted or removed from a Document, or when
	 * the 'id' attribute value of a Node is changed.
	 *
	 * @return Node|null null if there are no nodes
	 */
	public function getFirst() {
		if ( $this->first !== null ) {
			return $this->first;
		}

		// No item has been cached. Well, let's find it then.
		foreach ( $this->table as $index => $node ) {
			if ( $this->first === null ||
				 $this->first->compareDocumentPosition( $node ) & Node::DOCUMENT_POSITION_PRECEDING
			) {
				$this->first = $node;
			}
		}
		return $this->first;
	}

	/**
	 * If there is only one node left, return it. Otherwise return "this".
	 *
	 * @return Node|MultiId
	 */
	public function downgrade() {
		if ( $this->length === 1 ) {
			foreach ( $this->table as $index => $node ) {
				return $node;
			}
		}
		return $this;
	}
}
