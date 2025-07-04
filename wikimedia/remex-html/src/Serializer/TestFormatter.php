<?php

namespace Wikimedia\RemexHtml\Serializer;

use Wikimedia\RemexHtml\DOM\DOMFormatter;
use Wikimedia\RemexHtml\DOM\DOMUtils;
use Wikimedia\RemexHtml\HTMLData;
use Wikimedia\RemexHtml\Tokenizer\Attribute;

/**
 * A Formatter which is used to format documents in (almost) the way they
 * appear in the html5lib tests. A little bit of post-processing is required
 * in the PHPUnit tests.
 */
class TestFormatter implements Formatter, DOMFormatter {
	private const ATTR_NAMESPACES = [
		HTMLData::NS_XML => 'xml',
		HTMLData::NS_XLINK => 'xlink',
		HTMLData::NS_XMLNS => 'xmlns',
	];

	/** @inheritDoc */
	public function startDocument( $fragmentNamespace, $fragmentName ) {
		return '';
	}

	/** @inheritDoc */
	public function doctype( $name, $public, $system ) {
		$ret = "<!DOCTYPE $name";
		if ( $public !== '' || $system !== '' ) {
			$ret .= " \"$public\" \"$system\"";
		}
		$ret .= ">\n";
		return $ret;
	}

	/** @inheritDoc */
	public function characters( SerializerNode $parent, $text, $start, $length ) {
		return $this->formatCharacters( substr( $text, $start, $length ) );
	}

	private function formatCharacters( string $text ): string {
		return '"' .
			str_replace( "\n", "<EOL>", $text ) .
			"\"\n";
	}

	/** @inheritDoc */
	public function element( SerializerNode $parent, SerializerNode $node, $contents ) {
		return $this->formatElement( $node->namespace, $node->name,
			$node->attrs->getObjects(), $contents );
	}

	private function formatElement( ?string $namespace, string $name, array $attrs, ?string $contents ): string {
		$name = DOMUtils::uncoerceName( $name );
		if ( $namespace === HTMLData::NS_HTML ) {
			$tagName = $name;
		} elseif ( $namespace === HTMLData::NS_SVG ) {
			$tagName = "svg $name";
		} elseif ( $namespace === HTMLData::NS_MATHML ) {
			$tagName = "math $name";
		} else {
			$tagName = $name;
		}
		$ret = "<$tagName>\n";
		$sortedAttrs = $attrs;
		ksort( $sortedAttrs, SORT_STRING );
		foreach ( $sortedAttrs as $attrName => $attr ) {
			$localName = DOMUtils::uncoerceName( $attr->localName );
			if ( $attr->namespaceURI === null
				|| isset( $attr->reallyNoNamespace )
			) {
				$prefix = '';
			} elseif ( isset( self::ATTR_NAMESPACES[$attr->namespaceURI] ) ) {
				$prefix = self::ATTR_NAMESPACES[$attr->namespaceURI] . ' ';
			} else {
				$prefix = '';
			}
			$ret .= "  $prefix$localName=\"{$attr->value}\"\n";
		}
		if ( $contents !== null && $contents !== '' ) {
			$contents = preg_replace( '/^/m', '  ', $contents );
		} else {
			$contents = '';
		}
		if ( $namespace === HTMLData::NS_HTML && $name === 'template' ) {
			if ( $contents === '' ) {
				$contents = "  content\n";
			} else {
				$contents = "  content\n" . preg_replace( '/^/m', '  ', $contents );
			}
		}
		$ret .= $contents;
		return $ret;
	}

	/** @inheritDoc */
	public function comment( SerializerNode $parent, $text ) {
		return $this->formatComment( $text );
	}

	private function formatComment( string $text ): string {
		return "<!-- $text -->\n";
	}

	/** @inheritDoc */
	public function formatDOMNode( $node ) {
		$contents = '';
		if ( $node->firstChild ) {
			foreach ( $node->childNodes as $child ) {
				$contents .= $this->formatDOMNode( $child );
			}
		}

		switch ( $node->nodeType ) {
			case XML_ELEMENT_NODE:
				'@phan-var \DOMElement $node'; /** @var \DOMElement $node */
				return $this->formatDOMElement( $node, $contents );

			case XML_DOCUMENT_NODE:
			case XML_DOCUMENT_FRAG_NODE:
				return $contents;

			case XML_TEXT_NODE:
			case XML_CDATA_SECTION_NODE:
				'@phan-var \DOMCharacterData $node'; /** @var \DOMCharacterData $node */
				return $this->formatCharacters( $node->data );

			case XML_COMMENT_NODE:
				'@phan-var \DOMComment $node'; /** @var \DOMComment $node */
				return $this->formatComment( $node->data );

			case XML_DOCUMENT_TYPE_NODE:
				'@phan-var \DOMDocumentType $node'; /** @var \DOMDocumentType $node */
				return $this->doctype( $node->name, $node->publicId, $node->systemId );

			case XML_PI_NODE:
			default:
				return '';
		}
	}

	/** @inheritDoc */
	public function formatDOMElement( $node, $content ) {
		$attrs = [];
		foreach ( $node->attributes as $attr ) {
			$prefix = null;
			switch ( $attr->namespaceURI ) {
				case HTMLData::NS_XML:
					$prefix = 'xml';
					$qName = 'xml:' . $attr->localName;
					break;
				case HTMLData::NS_XMLNS:
					if ( $attr->localName === 'xmlns' ) {
						$qName = 'xmlns';
					} else {
						$prefix = 'xmlns';
						$qName = 'xmlns:' . $attr->localName;
					}
					break;
				case HTMLData::NS_XLINK:
					$prefix = 'xlink';
					$qName = 'xlink:' . $attr->localName;
					break;
				default:
					if ( strlen( $attr->prefix ) ) {
						$qName = $attr->prefix . ':' . $attr->localName;
					} else {
						$prefix = $attr->prefix;
						$qName = $attr->localName;
					}
			}

			$attrs[$qName] = new Attribute( $qName, $attr->namespaceURI, $prefix,
				$attr->localName, $attr->value );
		}

		$qName = $node->prefix ? ( $node->prefix . ':' . $node->localName ) :
			   $node->localName;
		return $this->formatElement( $node->namespaceURI, $qName, $attrs, $content );
	}
}
