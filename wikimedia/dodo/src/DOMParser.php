<?php

declare( strict_types = 1 );

namespace Wikimedia\Dodo;

use Exception;
use RemexHtml\DOM\DOMBuilder;
use RemexHtml\Tokenizer\NullTokenHandler;
use RemexHtml\Tokenizer\Tokenizer;
use RemexHtml\TreeBuilder\Dispatcher;
use RemexHtml\TreeBuilder\TreeBuilder;
use Wikimedia\IDLeDOM\DOMParserSupportedType;
use XMLReader;

/**
 * DOMParser
 * @see https://dom.spec.whatwg.org/#interface-domparser
 */
class DOMParser implements \Wikimedia\IDLeDOM\DOMParser {

	/**
	 * @param string $string
	 * @param string $type
	 * @return Document
	 */
	public function parseFromString( string $string, /* DOMParserSupportedType */ string $type ) {
		$type = DOMParserSupportedType::cast( $type );
		switch ( $type ) {
		case DOMParserSupportedType::text_html:
			return $this->_parseHtml( $string );
		default:
			// XXX if we throw an XML well-formedness error here, we're
			/// supposed to make a document describing it, instead of
			// throwing an exception.
			return $this->_parseXml( $string, $type );
		}
	}

	/**
	 * Create an HTML parser, parsing the string as UTF-8.
	 * @param string $string
	 * @return Document
	 */
	private function _parseHtml( string $string ) {
		$domBuilder = new class( [
			'suppressHtmlNamespace' => true,
			'suppressIdAttribute' => true,
			'domExceptionClass' => DOMException::class,
		] ) extends DOMBuilder {
				/** @var Document */
				private $doc;

				/** @inheritDoc */
				protected function createDocument(
					string $doctypeName = null,
					string $public = null,
					string $system = null
				) {
					// Force this to be an HTML document (not an XML document)
					$this->doc = new Document( null, 'html', 'text/html' );
					if ( $doctypeName !== null && $doctypeName !== '' ) {
						$this->doc->appendChild( new DocumentType(
							$this->doc,
							$doctypeName,
							$public ?? '',
							$system ?? ''
						) );
					}
					return $this->doc;
				}

				/** @inheritDoc */
				public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
					parent::doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength );
					// Set quirks mode on our document.
					switch ( $quirks ) {
					case TreeBuilder::NO_QUIRKS:
						$this->doc->_setQuirksMode( 'no-quirks' );
						break;
					case TreeBuilder::LIMITED_QUIRKS:
						$this->doc->_setQuirksMode( 'limited-quirks' );
						break;
					case TreeBuilder::QUIRKS:
						$this->doc->_setQuirksMode( 'quirks' );
						break;
					}
				}
		};
		$treeBuilder = new TreeBuilder( $domBuilder, [
			'ignoreErrors' => true
		] );
		$dispatcher = new Dispatcher( $treeBuilder );
		$tokenizer = new Tokenizer( $dispatcher, $string, [
			'ignoreErrors' => true ]
		);
		$tokenizer->execute( [] );

		$result = $domBuilder->getFragment();
		return $result;
	}

	/**
	 * An XML parser ... is a construct that follows the rules given in
	 * XML to map a string of bytes or characters into a Document
	 * object.
	 *
	 * The spec then follows that up with:
	 * "Note: At the time of writing, no such rules actually exist."
	 *
	 * Use the enabled-by-default PHP XMLReader class to do our
	 * parsing and cram it into a Document somehow, and hope we don't
	 * mangle things too badly.
	 *
	 * @see https://html.spec.whatwg.org/multipage/xhtml.html#xml-parser
	 *
	 * @param string $s The string to parse
	 * @param string $contentType
	 * @return Document
	 */
	private function _parseXML( string $s, string $contentType ) {
		# The XMLReader class is cranky about empty strings.
		if ( $s === '' ) {
			throw new \Exception( "no root element found" );
		}
		$reader = new XMLReader();
		$reader->XML(
			$s, 'utf-8',
			LIBXML_NOERROR | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_PARSEHUGE
		);
		# According to spec, this is a Document not an XMLDocument
		$doc = new Document( null, 'xml', $contentType );
		$node = $doc;
		$attrNode = null;
		while ( $reader->moveToNextAttribute() || $reader->read() ) {
			switch ( $reader->nodeType ) {
			case XMLReader::END_ELEMENT:
				$node = $node->getParentNode();
				// Workaround to prevent us from visiting the attributes again
				while ( $reader->moveToNextAttribute() ) {
					/* skip */
				}
				break;
			case XMLReader::ELEMENT:
				$qname = $reader->prefix ?? '';
				if ( $qname !== '' ) {
					$qname .= ':';
				}
				$qname .= $reader->localName;
				// This will be the node we'll attach attributes to!
				$attrNode = $doc->createElementNS( $reader->namespaceURI, $qname );
				$node->appendChild( $attrNode );
				// We don't get an END_ELEMENT from the reader if this is
				// an empty element (sigh)
				if ( !$reader->isEmptyElement ) {
					$node = $attrNode;
				}
				break;
			case XMLReader::ATTRIBUTE:
				$qname = $reader->prefix ?? '';
				if ( $qname !== '' ) {
					$qname .= ':';
				}
				$qname .= $reader->localName;
				'@phan-var Element $attrNode';
				$attrNode->setAttributeNS(
					$reader->namespaceURI, $qname, $reader->value
				);
				break;
			case XMLReader::SIGNIFICANT_WHITESPACE:
			case XMLReader::TEXT:
				$nn = $doc->createTextNode( $reader->value );
				$node->appendChild( $nn );
				break;
			case XMLReader::CDATA:
				$nn = $doc->createCDATASection( $reader->value );
				$node->appendChild( $nn );
				break;
			case XMLReader::DOC_TYPE:
				# This is a hack: the PHP XMLReader interface provides no
				# way to extract the contents of a DOC_TYPE node!  So we're
				# going to give it to the HTML tokenizer to interpret.
				$tokenHandler = new class extends NullTokenHandler {
					/** @var string */
					public $name;
					/** @var string */
					public $publicId;
					/** @var string */
					public $systemId;

					/** @inheritDoc */
					public function doctype(
						$name, $publicId, $systemId,
						$quirks, $sourceStart, $sourceLength
					) {
						$this->name = $name;
						$this->publicId = $publicId;
						$this->systemId = $systemId;
					}
				};
				( new Tokenizer(
					$tokenHandler, $reader->readOuterXml(), []
				) )->execute( [] );
				$nn = $doc->getImplementation()->createDocumentType(
					$tokenHandler->name ?? '',
					$tokenHandler->publicId ?? '',
					$tokenHandler->systemId ?? ''
				);
				$node->appendChild( $nn );
				break;
			default:
				throw new Exception( "Unknown node type: " . $reader->nodeType );
			}
		}
		return $doc;
	}
}
