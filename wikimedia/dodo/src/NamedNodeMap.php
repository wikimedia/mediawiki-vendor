<?php

declare( strict_types = 1 );
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable

namespace Wikimedia\Dodo;

use Wikimedia\Dodo\Internal\UnimplementedTrait;
use Wikimedia\Dodo\Internal\Util;

/******************************************************************************
 * NamedNodeMap.php
 * ----------------
 * Implements a NamedNodeMap. Used to represent Element::attributes.
 *
 * NOTE: Why is it called NamedNodeMap?
 *
 *      NamedNodeMap has nothing to do with Nodes, it's a collection
 *      of Attrs. But once upon a time, an Attr was a type of Node called a
 *      NamedNode. But then DOM-4 came along and said that an Attr is no
 *      longer a subclass of Node. But then DOM-LS came and change it again,
 *      and said it was a subclass of Node. NamedNode was forgotten, but it
 *      lives on in this interface's name! How confusing!
 *
 * NOTE: This looks different from Domino.js!
 *
 *      In Domino.js, NamedNodeMap was only implemented to satisfy
 *      'instanceof' type-checking. Almost all of the methods were
 *      stubbed, except for 'length' and 'item'. The tables that
 *      stored an Element's attributes were located directly on the
 *      Element itself.
 *
 *      Because there are so many attribute handling methods on an
 *      Element, each with little differences, this meant replicating
 *      a bunch of the book-keeping inside those methods. The negative
 *      impact on code maintainability was pronounced, so the book-keeping
 *      was transferred to the NamedNodeMap itself, and its methods were
 *      properly implemented, which made it much easier to read and write
 *      the attribute methods on the Element class.
 *
 */
class NamedNodeMap implements \Wikimedia\IDLeDOM\NamedNodeMap {
	// Stub out methods not yet implemented.
	use \Wikimedia\IDLeDOM\Stub\NamedNodeMap;
	use UnimplementedTrait;

	// Helper functions from IDLeDOM
	use \Wikimedia\IDLeDOM\Helper\NamedNodeMap;

	/**
	 * qname => Attr
	 *
	 * @var array entries are either Attr objects, or arrays of Attr objects on collisions
	 */
	private $_qname_to_attr = [];

	/**
	 * ns|lname => Attr
	 *
	 * @var Attr[]
	 */
	private $_lname_to_attr = [];

	/**
	 * ns|lname => index number
	 *
	 * @var int[]
	 */
	private $_lname_to_index = [];

	/**
	 * index number => Attr
	 * @var Attr[]
	 */
	private $_index_to_attr = [];

	/**
	 * DOM-LS associated element, defined in spec but not given property.
	 *
	 * @var ?Element
	 */
	private $_element = null;

	/**
	 * @param ?Element $element
	 */
	public function __construct( ?Element $element = null ) {
		$this->_element = $element;
	}

	/**********************************************************************
	 * DODO INTERNAL BOOK-KEEPING
	 */

	/**
	 * @param Attr $a
	 */
	private function _append( Attr $a ) {
		$qname = $a->getName();

		/* NO COLLISION */
		if ( !isset( $this->_qname_to_attr[$qname] ) ) {
			$this->_qname_to_attr[$qname] = $a;
			/* COLLISION */
		} else {
			if ( is_array( $this->_qname_to_attr[$qname] ) ) {
				$this->_qname_to_attr[$qname][] = $a;
			} else {
				$this->_qname_to_attr[$qname] = [
					$this->_qname_to_attr[$qname],
					$a
				];
			}
		}

		$key = $a->getNamespaceURI() . '|' . $a->getLocalName();

		$this->_lname_to_attr[$key] = $a;
		$this->_lname_to_index[$key] = count( $this->_index_to_attr );
		$this->_index_to_attr[] = $a;

		$a->_handleAttributeChanges( $a->getOwnerElement(), null, $a->getValue() );
	}

	/**
	 * @param Attr $oldAttr
	 * @param Attr $a
	 */
	private function _replace( Attr $oldAttr, Attr $a ) {
		$qname = $a->getName();

		/* NO COLLISION */
		if ( !isset( $this->_qname_to_attr[$qname] ) ) {
			$this->_qname_to_attr[$qname] = $a;
			/* COLLISION */
		} else {
			if ( is_array( $this->_qname_to_attr[$qname] ) ) {
				$this->_qname_to_attr[$qname][] = $a;
			} else {
				$this->_qname_to_attr[$qname] = [
					$this->_qname_to_attr[$qname],
					$a
				];
			}
		}

		$key = $a->getNamespaceURI() . '|' . $a->getLocalName();

		$this->_lname_to_attr[$key] = $a;
		$this->_index_to_attr[$this->_lname_to_index[$key]] = $a;

		$a->_handleAttributeChanges( $a->getOwnerElement(), $oldAttr->getValue(), $a->getValue() );
	}

	/**
	 * @internal
	 * @param Attr $a
	 */
	public function _remove( Attr $a ) : void {
		$qname = $a->getName();
		$key = $a->getNamespaceURI() . '|' . $a->getLocalName();

		unset( $this->_lname_to_attr[$key] );
		$i = $this->_lname_to_index[$key];
		unset( $this->_lname_to_index[$key] );

		array_splice( $this->_index_to_attr, $i, 1 );

		if ( isset( $this->_qname_to_attr[$qname] ) ) {
			if ( is_array( $this->_qname_to_attr[$qname] ) ) {
				$i = array_search( $a, $this->_qname_to_attr[$qname] );
				if ( $i !== false ) {
					array_splice( $this->_qname_to_attr[$qname], $i, 1 );
				}
			} else {
				unset( $this->_qname_to_attr[$qname] );
			}
			$el = $a->getOwnerElement();
			$a->_ownerElement = null;
			$a->_handleAttributeChanges( $el, $a->getValue(), null );
			return;
		}
		Util::error( 'NotFoundError' );
	}

	/*
	 * DOM-LS Methods
	 */

	/** @inheritDoc */
	public function getLength(): int {
		return count( $this->_index_to_attr );
	}

	/** @inheritDoc */
	public function item( int $index ) {
		return $this->_index_to_attr[$index] ?? null;
	}

	/**
	 * Nonstandard.
	 * @inheritDoc
	 */
	public function _hasNamedItem( string $qname ): bool {
		/*
		 * Per HTML spec, we normalize qname before lookup,
		 * even though XML itself is case-sensitive.
		 */
		if ( !ctype_lower( $qname ) && $this->_element->_isHTMLElement() ) {
			$qname = Util::toAsciiLowercase( $qname );
		}

		return isset( $this->_qname_to_attr[$qname] );
	}

	/**
	 * Nonstandard.
	 * @inheritDoc
	 */
	public function _hasNamedItemNS( ?string $ns, string $lname ): bool {
		$ns = $ns ?? "";
		return isset( $this->_lname_to_attr["$ns|$lname"] );
	}

	/** @inheritDoc */
	public function getNamedItem( string $qname ) : ?Attr {
		/*
		 * Per HTML spec, we normalize qname before lookup,
		 * even though XML itself is case-sensitive.
		 */
		if ( !ctype_lower( $qname ) && $this->_element->_isHTMLElement() ) {
			$qname = Util::toAsciiLowercase( $qname );
		}

		if ( !isset( $this->_qname_to_attr[$qname] ) ) {
			return null;
		}

		if ( is_array( $this->_qname_to_attr[$qname] ) ) {
			return $this->_qname_to_attr[$qname][0];
		} else {
			return $this->_qname_to_attr[$qname];
		}
	}

	/** @inheritDoc */
	public function getNamedItemNS( ?string $ns, string $lname ) : ?Attr {
		$ns = $ns ?? "";
		return $this->_lname_to_attr["$ns|$lname"] ?? null;
	}

	/** @inheritDoc */
	public function setNamedItem( $attr ) {
		'@phan-var Attr $attr'; // @var Attr $attr
		$owner = $attr->getOwnerElement();

		if ( $owner !== null && $owner !== $this->_element ) {
			Util::error( "InUseAttributeError" );
		}

		$oldAttr = $this->getNamedItem( $attr->getName() );

		if ( $oldAttr == $attr ) {
			return $attr;
		}

		if ( $oldAttr !== null ) {
			$this->_replace( $oldAttr, $attr );
		} else {
			$this->_append( $attr );
		}

		return $oldAttr;
	}

	/** @inheritDoc */
	public function setNamedItemNS( $attr ) {
		'@phan-var Attr $attr'; // @var Attr $attr
		$owner = $attr->getOwnerElement();

		if ( $owner !== null && $owner !== $this->_element ) {
			Util::error( "InUseAttributeError" );
		}

		$oldAttr = $this->getNamedItemNS( $attr->getNamespaceURI(), $attr->getLocalName() );

		if ( $oldAttr === $attr ) {
			return $attr;
		}

		if ( $oldAttr !== null ) {
			$this->_replace( $oldAttr, $attr );
		} else {
			$this->_append( $attr );
		}

		return $oldAttr;
	}

	/**
	 * Note: qname may be lowercase or normalized in various ways
	 *
	 * @inheritDoc
	 */
	public function removeNamedItem( string $qname ): Attr {
		$attr = $this->getNamedItem( $qname );
		if ( $attr !== null ) {
			'@phan-var Attr $attr'; // @var Attr $attr
			$this->_remove( $attr );
		} else {
			Util::error( "NotFoundError" );
			// Lie to phan about types since the above should never return
			'@phan-var Attr $attr'; // @var Attr $attr
		}
		return $attr;
	}

	/**
	 * Note: lname may be lowercase or normalized in various ways
	 *
	 * @inheritDoc
	 */
	public function removeNamedItemNS( ?string $ns, string $lname ) {
		$attr = $this->getNamedItemNS( $ns, $lname );
		if ( $attr !== null ) {
			'@phan-var Attr $attr'; // @var Attr $attr
			$this->_remove( $attr );
		} else {
			Util::error( "NotFoundError" );
			// Lie to phan about types since the above should never return
			'@phan-var Attr $attr'; // @var Attr $attr
		}
		return $attr;
	}
}
