<?php

declare( strict_types=1 );

namespace Wikimedia\Dodo\Tools\TestsGenerator;

use Robo\Common\ExecOneCommand;
use Robo\Result;
use Robo\Task\BaseTask;
use Symfony\Component\Finder\Finder;

/**
 * Class TestsLocator
 *
 * @package DodoTestsGenerator\Robo\Task\Locator
 *
 */
class LocatorTask extends BaseTask {

	use ExecOneCommand;

	private const W3C_TESTS = "/vendor/fgnass/domino/test/w3c/level1";

	private const WPT_TESTS = "/vendor/web-platform-tests/wpt";

	/**
	 * @var string[]
	 */
	public static $skips = [
		'DOMImplementation-createDocument' => 'js2php or parsing issue',
		'Document-createProcessingInstruction' => 'js2php or parsing issue',
		'Element-classlist' => 'js2php or parsing issue',
		'MutationObserver-document' => 'js2php or parsing issue',
		'Node-baseURI' => 'js2php or parsing issue',
		'Node-childNodes' => 'js2php or parsing issue',
		'Node-cloneNode-document-with-doctype' => 'js2php or parsing issue',
		'Node-parentNode-iframe' => 'js2php or parsing issue',
		'Node-properties' => 'js2php or parsing issue',
		'attributes-namednodemap' => 'js2php or parsing issue',
		'HTMLCollection-supported-property-indices' => 'js2php or parsing issue',
		'DOMTokenList-iteration' => 'js2php or parsing issue',
		'Range-comparePoint' => 'js2php or parsing issue',
		'Range-isPointInRange' => 'js2php or parsing issue',
		'TreeWalker' => 'js2php or parsing issue',
		'HTMLCollection-supported-property-names' => 'js2php or parsing issue',
		'Node-cloneNode' => 'js2php or parsing issue',
		'ParentNode-querySelectorAll-removed-elements' => 'js2php or parsing issue',
		'case' => 'Syntax error, unexpected T_USE, expecting \'{\' on line 57.',
		'remove-unscopable' => 'uses window & dispatchEvent',
		'remove-from-shadow-host-and-adopt-into-iframe.html' => 'TakeScreenshot.',
		'remove-and-adopt-thcrash' => 'uses window, tests a Chrome to crash.',
		'query-target-in-load-event' => 'uses addEventListener.',
		'remove-from-shadow-host-and-adopt-into-iframe' => 'takeScreenshot.',
		'ParentNode-replaceChildren' => 'MutationObserver.',
		'MutationObserver*' => 'MutationObserver.',
		'Range-mutations-*' => 'difficult to parse and convert to PHP.',
		'Comment-constructor' => 'CommentConstructorTest - Object::getPrototypeOf(Object::getPrototypeOf($object))',
		'Document-characterSet-normalization' => 'async test',
		'DocumentCreateElementTest' => 'Window and addEventListener',
		'DocumentCreateElementNSTest' => 'Window and addEventListener, could be transformed',
		'attributes' => 'uses asyncTest.',
		'comment-constructor.html' => 'Object::getPrototypeOf(Object::getPrototypeOf($object)',
		'Document-characterSet-normalization' => 'asyncTest',
		'Document-createElementNS' => 'addEventListener',
		'Document-createElement' => 'asyncTest and addEventListener',
		'Document-getElementsByTagName' => 'HTMLCollection::prototype::namedItem',
		'Document-URL' => '$iframe->onload = $this->step_func_done(function () use(&$iframe) {',
		'Text-constructor' => 'Object::getPrototypeOf(Object::getPrototypeOf($object)',
		'aria-element-reflection.tentative' => 'hard to make valid',
		'Element-getElementsByTagName' => 'HTMLCollection::prototype::item',
		'Node-replaceChild' => 'Node::class::replaceChild',
		'Range-test-iframe' => 'Uses onload event to run test, no sence to convert',
		'DOMImplementation-createHTMLDocument-with-null-browsing-context-crash' => 'Pointless, tests browser',
		'DOMImplementation-createDocument-with-null-browsing-context-crash' => 'Same as previous',
		'ParentNode-querySelector-All' => 'Not applicable at the moment',
		'DOMParser-parseFromString-url-moretests' => 'js2php or parsing issue',
		'DOMParser-parseFromString-xml-internal-subset' => 'js2php or parsing issue',
		'DOMParser-parseFromString-xml-parsererror' => 'js2php or parsing issue',
	];

	/**
	 * @var Finder
	 */
	private $w3c_tests;
	/**
	 * @var Finder
	 */
	private $wpt_tests;

	/**
	 * @var Finder
	 */
	private $wpt_harness;

	/**
	 * @var Finder
	 */
	private $w3c_harness;
	/**
	 * @var false|string
	 */
	private $folder;

	/**
	 * FileLoader constructor.
	 *
	 * @param null|string $folder
	 */
	public function __construct( ?string $folder ) {
		$this->folder = $folder ?? realpath( __DIR__ . "/../.." );
	}

	/**
	 * @return Result
	 */
	public function run() : Result {
		$exclude_dirs = [ 'obsolete',
			'nyi' ];
		$w3c_tests_path = $this->folder . self::W3C_TESTS;
		$this->w3c_tests = ( new Finder() )->name( '*.js' )->exclude( $exclude_dirs )->in( $w3c_tests_path )
			->ignoreUnreadableDirs()->files()->sortByName();

		self::$skips = array_keys( self::$skips );

		array_walk( self::$skips,
			static function ( &$item, $key ) {
				$item .= '.*';
			} );

		/**
		 * For now only load .html's
		 */
		$exclude_dirs = [
			'Document-createElement-namespace-tests',
			'unfinished',
			'support',
			'Document-contentType',
		];
		$wpt_tests_path = $this->folder . self::WPT_TESTS;

		$subfolders = [
			'/dom/nodes',
			'/dom/collections',
			'/dom/traversal',
			'/dom/ranges',
			'/dom/lists',
			'/domparsing',
		];

		$subfolders = preg_filter( '/^/',
			$wpt_tests_path,
			$subfolders );

		$this->wpt_tests = ( new Finder() )->name( [ "*.html" ] )->notName( self::$skips )->in(
			$subfolders )
			->exclude( $exclude_dirs )->ignoreUnreadableDirs()
			->files()->sortByName();

		if ( !$this->w3c_tests->hasResults() ) {
			return Result::error( $this,
				'No W3C tests were found.' );
		}

		if ( !$this->wpt_tests->hasResults() ) {
			return Result::error( $this,
				'No WPT tests were found.' );
		}

		$tests = [
			'W3C' => iterator_to_array( $this->w3c_tests ),
			'WPT' => iterator_to_array( $this->wpt_tests )
		];

		$w3c_count = count( $tests['W3C'] );
		$wpt_count = count( $tests['WPT'] );

		$this->printTaskInfo( 'W3C tests: ' . $w3c_count );
		$this->printTaskInfo( 'WPT tests: ' . $wpt_count );
		$this->printTaskInfo( 'Total tests: ' . ( $w3c_count + $wpt_count ) );

		return Result::success( $this,
			'All good.',
			$tests );
	}
}
