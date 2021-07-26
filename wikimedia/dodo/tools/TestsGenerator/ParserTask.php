<?php

declare( strict_types=1 );
// @phan-file-suppress PhanTypeMismatchArgumentInternal
// @phan-file-suppress PhanUndeclaredProperty

namespace Wikimedia\Dodo\Tools\TestsGenerator;

use PhpParser\BuilderFactory;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use Robo\Result;
use Robo\Task\BaseTask;

/**
 * Class ParserTask
 *
 * @package Wikimedia\Dodo\Tools\TestsGenerator
 */
class ParserTask extends BaseTask {
	use Helpers;

	/**
	 * @var string
	 */
	private $test;

	/**
	 * @var Parser
	 */
	private $parser;

	/**
	 * @var NodeFinder
	 */
	private $finder;

	/**
	 * @var NodeDumper
	 */
	private $dumper;

	/**
	 * @var BuilderFactory
	 */
	private $factory;

	/**
	 * @var string
	 */
	private $test_type;

	/**
	 * @var string
	 */
	private $test_name;

	/**
	 * @var string
	 */
	private $file;

	/**
	 * @var bool
	 */
	private $wrap_only;

	/**
	 * @var bool
	 */
	private $compact;

	/**
	 * @var string
	 */
	private $test_path;

	/**
	 * ParserTask constructor.
	 *
	 * TODO rewrite parameters with one args array.
	 *
	 * @param string $test
	 * @param string $test_name
	 * @param string $test_type
	 * @param bool $compact
	 * @param bool $wrap_only
	 * @param string|null $test_path
	 */
	public function __construct( string $test, string $test_name, string $test_type,
		bool $compact = false, bool $wrap_only = false, ?string $test_path = null ) {
		$this->test = $test;
		$this->finder = new NodeFinder;
		$this->parser = ( new ParserFactory )->create( ParserFactory::ONLY_PHP7 );
		$this->dumper = new NodeDumper;
		$this->factory = new BuilderFactory;
		$this->test_type = $test_type;
		$this->test_name = $test_name;
		$this->test_path = $this->getRealpath( $test_path ) ?: $test_name;
		$this->compact = $compact;
		$this->wrap_only = $wrap_only;
	}

	/**
	 * Strip the part of the given path which is outside of this repository.
	 *
	 * @param string $path
	 *
	 * @return string The stripped path
	 */
	private function getRealpath( string $path ) : string {
		$path = realpath( $path );
		$base = realpath( __DIR__ . '/../..' );
		$min = ( strlen( $path ) < strlen( $base ) ) ? strlen( $path ) : strlen( $base );
		for ( $i = 0; $i < $min; $i++ ) {
			if ( $path[$i] !== $base[$i] ) {
				break;
			}
		}

		return ltrim( substr( $path, $i ), '/' );
	}

	/**
	 * @return Result
	 */
	public function run() : Result {
		try {
			$this->preprocessTest();

			if ( $this->wrap_only ) {
				// TestWrapper is need only for proper parsing.
				$ast = $this->parser->parse( '<?php class TestWrapper {' . $this->test . '}' );
				$this->wrapInClass( $ast );
			}

			if ( $this->test_type === TestsGenerator::W3C ) {
				$this->preProcessW3CTest();
				$ast = $this->parser->parse( '<?php ' . $this->test );
				$this->parseW3CTest( $ast );
				$this->removeW3CDisparity();
			}

			if ( $this->test_type === TestsGenerator::WPT ) {
				$this->preProcessWPTTest();
				$ast = $this->parser->parse( '<?php ' . $this->test );
				$this->parseWPTTest( $ast );
				$this->postProcessWPTTest();
			}
		} catch ( Error $error ) {
			return Result::error( $this,
				"Parse error: {$error->getMessage()}\n",
				[ $this->test ] );
		}

		return Result::success( $this,
			'All good.',
			[ $this->test ] );
	}

	/**
	 *
	 */
	protected function preprocessTest() {
		$find_replace = [ 'new Array()' => '[]' ];
		$this->test = strtr( $this->test, $find_replace );
	}

	/**
	 * @param array $stmts
	 */
	protected function wrapInClass( array $stmts ) {
		$stmts = $stmts[0]->stmts;

		if ( $this->test_type === TestsGenerator::W3C ) {
			$class = $this->factory->class( $this->snakeToCamel( $this->test_name ) . 'Test' )->extend( 'DomTestCase' )
				->addStmts( $stmts )->getNode();
			$stmts = $this->factory->namespace( 'Wikimedia\Dodo\Tests' )
				->addStmts( [ $this->factory->use( 'Exception' ),
					$class ] )->getNode();
		} else {
			// create test class
			$class = $this->factory->class( $this->test_name . 'Test' )->extend( 'DodoBaseTest' )->addStmts( $stmts )
				->getNode();
			$use_stmts = $this->factory->use( 'Wikimedia\Dodo\Document' );
			$stmts = $this->factory->namespace( 'Wikimedia\Dodo\Tests' )->addStmts( [ $use_stmts,
				$class ] )->getNode();
		}

		$prettyPrinter = new PrettyPrinter\Standard();
		$this->test = "<?php \n" . $prettyPrinter->prettyPrint( [ $stmts ] );
	}

	/**
	 * @param array $ast
	 */
	protected function parseW3CTest( array $ast ) : void {
		$ast = $this->prepareAst( $ast );
		$traverser = new NodeTraverser;
		$visitor = new class( $this->test_name, $this->parser, $this->factory ) extends NodeVisitorAbstract {
			use Helpers;

			/**
			 * @var string
			 */
			public $test_name;
			/**
			 * @var Parser
			 */
			private $parser;
			/**
			 * @var BuilderFactory
			 */
			private $factory;
			/**
			 * @var array<string,bool>
			 */
			public $uses;

			/**
			 *  constructor.
			 *
			 * @param string $test_name
			 * @param Parser $parser
			 * @param BuilderFactory $factory
			 */
			public function __construct(
				string $test_name, Parser $parser, BuilderFactory $factory
			) {
				$this->test_name = $test_name;
				$this->parser = $parser;
				$this->factory = $factory;
				$this->uses = [];
			}

			/**
			 * @param mixed $node
			 *
			 * @return int|Node|Function_
			 */
			public function leaveNode( $node ) {
				$node_type = $node->getType();
				if ( $node instanceof Function_ && $node->name->toString() === $this->test_name ) {
					$test_method = $this->snakeToCamel( 'test ' . $node->name );
					return $this->factory->method( $test_method )->makePublic()->addStmts( $node->getStmts() )
						->getNode();
				}

				if ( $node instanceof FuncCall ) {
					$expr_name = $node->name->parts[0] ?? '';
					if ( empty( $expr_name ) ) {
						return $node;
					}

					$harness_functions = array_flip( [
						'getImplementation',
						'checkInitialization',
						'load',
						'createConfiguredBuilder',
						'setImplementationAttribute',
						'preload',
						'catchInitializationError',
						'checkFeature',
						'fail', // it's not clear where this one is defined!
						// assertion methods
						'assertEquals',
						'assertEqualsAutoCase',
						'assertEqualsCollection',
						'assertEqualsList',
						'assertEqualsListAutoCase',
						'assertFalse',
						'assertNotNull',
						'assertNull',
						'assertSame',
						'assertSize',
						'assertTrue',
						'assertURIEquals',
					] );

					if ( array_key_exists( $expr_name, $harness_functions ) ) {
						$args = $node->args;

						if (
							strpos( $expr_name, 'assert' ) === 0 ||
							$expr_name === 'fail'
						) {
							// TestCase already has methods named assert*
							// and one called 'fail', so add a 'w3c' prefix to
							// these harness functions.
							$call = new Node\Expr\MethodCall(
								new Variable( 'this' ),
								$this->snakeToCamel( 'w3c_' . $expr_name ),
								$args,
								$node->getAttributes() );
						} else {
							$call = new Node\Expr\MethodCall( new Variable( 'this' ),
								$this->snakeToCamel( $expr_name ),
								$args,
								$node->getAttributes() );
						}

						return $call;
					}
				}

				// remove all other functions
				if ( $node instanceof Comment || $node instanceof Doc ) {
					return NodeTraverser::REMOVE_NODE;
				}

				if ( $node instanceof Function_ ) {
					$remove = [ 'toASCIIUppercase', 'toASCIILowercase' ];
					$func_name = $node->name->name;
					if ( preg_match( '(' . implode( '|', $remove ) . ')', $func_name ) === 1 ) {
						return NodeTraverser::REMOVE_NODE;
					}

					$node = $this->factory->method( $this->snakeToCamel( $func_name ) )->makePublic()
						->addStmts( $node->stmts )->addParams( $node->getParams() )->getNode();

					return $node;
				}
			}
		};
		$traverser->addVisitor( $visitor );

		/*
		$visitor = new class extends NodeVisitorAbstract
		{
			use Helpers;

			/**
			 * @var array<string,bool>
			 * /
			public $uses = [];

			/**
			 * @param mixed $node
			 *
			 * @return int|Node|Function_
			 * /
			public function leaveNode( $node ) {
				if ( $node instanceof If_ ) {
					$left_part = $node->cond->left ?? null;

					if ( $left_part ) {
						if ( isset( $left_part->name->parts ) &&
							$left_part->name->parts[0] === 'gettype' &&
							$left_part->args[0]->value->name->name === 'code' &&
							$left_part->args[0]->value->var->name === 'ex' ) {
							$ast = $this->parser->parse( '<?php ' .
								'$this->assertEquals( DOMException::NO_MODIFICATION_ALLOWED_ERR,
								$ex->getCode());' );

							return reset( $ast );
						}
					}
				}
			}
		};
		$traverser->addVisitor( $visitor );
		*/

		$stmts = $traverser->traverse( $ast );

		if ( !$this->compact ) {
			$class = $this->factory->class( $this->snakeToPascal( $this->test_name ) . 'Test' )
				->extend( 'W3CTestHarness' )->addStmts( $stmts )->setDocComment( '// @see ' . $this->test_path . '.' )
				->getNode();
			$use_stmts = $this->getUseStmts( $visitor->uses );

			$stmts = $this->factory->namespace( 'Wikimedia\Dodo\Tests\W3C' )->addStmts( $use_stmts )->addStmts( [
				$class ] )->getNode();
		}

		$this->prettyPrint( $stmts );
	}

	/**
	 * @param mixed $stmts
	 */
	protected function prettyPrint( $stmts ) {
		if ( is_array( $stmts ) && count( $stmts ) == 1 ) {
			$stmts = array_pop( $stmts );
		}
		$prettyPrinter = new PrettyPrinter\Standard();
		if ( !is_array( $stmts ) ) {
			$stmts = [ $stmts ];
		}
		if ( $this->compact && $this->test_type === TestsGenerator::W3C ) {
			$this->test = $prettyPrinter->prettyPrint( $stmts );
		} else {
			$this->test = "<?php \n" . $prettyPrinter->prettyPrint( $stmts ) . "\n";
		}
	}

	/**
	 * Removes disparity for W3C tests.
	 */
	protected function removeW3CDisparity() : void {
		// Remove unnecessary empty lines.
		$this->test = preg_replace( '/^[ \t]*[\r\n]+/m', '', $this->test );
	}

	/**
	 * Removes disparity after js2php.
	 */
	protected function preProcessWPTTest() : void {
		$find_replace = [
			'$document::URL' => '$this->doc->URL',
			'$document' => '$this->doc',
			'= create(' => '= $this->create(',
			'= eval(' => '= $this->wptEvalNode(',
			'( eval(' => '( $this->wptEvalNode(',
			'$TypeError' => '$this->type_error',
			'Object::keys( $testExtensions )->' => '$testExtensions->',
			'new DOMParser()' => '(new DOMParser())',
			'$win::' => "",
			'new XMLHttpRequest();' => '$this->xmlHttpRequest();',
			'runTest' => 'runTestData',
			'\ELEMENT_NODE' => '::ELEMENT_NODE',
			'async_test' => '$this->asyncTest',
			'do_test(' => 'doTest(',
			'test(' => '$this->assertTest(',
			'Node\\' => 'Node::',
			'setup( [ \'single_test\' => true ] );' => '',
			'NodeFilter\\' => 'NodeFilter::',
			'( String(' => '( strval(',
			'= String(' => '= strval(',
			', String(' => ', strval(',
			'->contentDocument' => '->getOwnerDocument()',
			'append( $t, $tag, $name )' => 'append( $t, $tag, $name = \'\' )',
			'$activedescendant' => '$this->activedescendant',
			'->toString()' => '',
			'instanceof $Attr' => 'instanceof Attr',
			'$pair->attr' => '$pair[\'attr\']',
			'instanceof $Node' => 'instanceof Node',
			'instanceof $Document' => 'instanceof Document',
			'instanceof $XMLDocument' => 'instanceof XMLDocument',
			'instanceof $HTMLElement' => 'instanceof HTMLElement',
			'instanceof $HTMLSpanElement' => 'instanceof HTMLSpanElement',
			'instanceof $Element' => 'instanceof Element',
			'instanceof $HTMLDivElement' => 'instanceof HTMLDivElement',
			'instanceof $HTMLCollection' => 'instanceof HTMLCollection',
			'instanceof $CharacterData' => 'instanceof CharacterData',
			'instanceof $HTMLUnknownElement' => 'instanceof HTMLUnknownElement',
			'instanceof $DOMImplementation' => 'instanceof DOMImplementation',
			'instanceof $HTMLHeadElement' => 'instanceof HTMLHeadElement',
			'instanceof $HTMLBodyElement' => 'instanceof HTMLBodyElement',
			'instanceof $HTMLTitleElement' => 'instanceof HTMLTitleElement',
			'instanceof $HTMLHtmlElement' => 'instanceof HTMLHtmlElement',
			'+= strtoupper' => '.= strtoupper',
			'Object::keys' => 'get_object_vars',
			'$Text' => 'Text',
			'$Comment' => 'Comment',
			'$Element' => 'Element',
			'$Node' => 'Node',
			'$HTMLAnchorElement' => 'HTMLAnchorElement::class',
			'$HTMLHtmlElement' => 'HTMLHtmlElement::class',
			'$HTMLParagraphElement' => 'HTMLParagraphElement::class',
			'$XMLDocument' => 'XMLDocument',
			'function insert( $parent, $node ) use ( &$methodName )' =>
				'function insert( $parent, $node, &$methodName )',
			// temporary.
			'function ( $params ) { return function () use ( &$params ) {' =>
				'function ( $params ) { return function ( &$params ) {',
			// temporary.
			'new DocumentFragment()' => 'new DocumentFragment($this->doc)',
			'MutationEvent\\' => 'MutationEvent::',
			'Object::getPrototypeOf' => 'get_class',
			'Document::prototype' => 'Document::class',
			'Node::prototype' => 'Node::class',
			'->previousSibling' => '->getPreviousSibling()',
			// implicit cast of null to string is a JS feature; PHP uses
			// strict typing.  WPT/Dom/Nodes/DocumentGetElementByIdTest.php
			'->getElementById( null )' => '->getElementById( "null" )',
			'->setAttribute( \'id\', NULL )' => '->setAttribute( \'id\', "null" )',
		];

		$this->test = strtr( $this->test, $find_replace );

		// Remove unnecessary empty lines.
		$this->test = preg_replace( '/^[ \t]*[\r\n]+/m', '', $this->test );
	}

	/**
	 * Converts variable to class property.
	 * eg. converts $_v to $this->_v.
	 *
	 * @param array $list
	 *
	 * @return array|false
	 */
	protected function convertVarToClassVar( array $list ) {
		$result = [];
		foreach ( $list as $value ) {
			$result[$value] = '$this->' . ltrim( $value, '$' );
		}
		return $result;
	}

	/**
	 * Converts variable to static property of Common class
	 * eg. converts $_v to Common::_v.
	 *
	 * @param array $list
	 *
	 * @return array|false
	 */
	protected function convertVarToCommonVar( array $list ) {
		$result = [];
		foreach ( $list as $value ) {
			$result[$value] = '$this->getCommon()->' . ltrim( $value, '$' );
		}
		return $result;
	}

	/**
	 * Parses WPT test
	 *
	 * @param array $ast
	 */
	protected function parseWPTTest( array $ast ) : void {
		$stmts = $this->prepareAst( $ast );

		$traverser = new NodeTraverser;

		// $dump = $this->dumper->dump( $stmts ) . "n";

		$visitor = new class( $this->test_name, $this->parser, $this->factory ) extends NodeVisitorAbstract {
			use Helpers;

			/**
			 * @var string
			 */
			public $test_name;
			/**
			 * @var Parser
			 */
			public $parser;
			/**
			 * @var BuilderFactory
			 */
			private $factory;
			/**
			 * @var array<string,bool>
			 */
			public $uses;

			/**
			 *  constructor.
			 *
			 * @param string $test_name
			 * @param Parser $parser
			 * @param BuilderFactory $factory
			 */
			public function __construct(
				string $test_name, Parser $parser, BuilderFactory $factory
			) {
				$this->test_name = $test_name;
				$this->parser = $parser;
				$this->factory = $factory;
				$this->uses = [];
			}

			/**
			 * @param mixed $node
			 *
			 * @return int|Node|Function_
			 */
			public function leaveNode( $node ) {
				if ( $node instanceof Expression && $node->expr instanceof Node\Expr\FuncCall ) {
					$name = $node->expr->name->parts[0] ?? null;
					if ( $name && $name === 'setup' ) {
						$stmts = $node->expr->args[0]->value;
						if ( property_exists( $stmts,
							'stmts' ) ) {
							$stmts->stmts[0]->setDocComment( new Comment\Doc( '// setup()' ) );

							return $stmts->stmts;
						}
					}
				}
				if ( $node instanceof Expression && $node->expr instanceof Node\Expr\MethodCall ) {
					// forEach constructions fix.
					if ( $node->expr->name->name === 'forEach' ) {
						$for_expr = '';
						if ( !empty( ( $node->expr->var->name ) ) ) {
							$for_expr = new Node\Expr\Variable( $node->expr->var->name );
						}

						if ( $node->expr->var instanceof Node\Expr\ArrayDimFetch ) {
							$for_expr = new Node\Expr\Variable( $node->expr->var->var->name );
						}

						if ( $node->expr->var instanceof Node\Expr\Array_ ) {
							$for_expr = $node->expr->var;
						}

						$new_cycle = new Node\Stmt\Foreach_( $for_expr,
							new Node\Expr\Variable( $node->expr->args[0]->value->params[0]->var->name ) );
						$new_cycle->stmts = $node->expr->args[0]->value->stmts;

						return $new_cycle;
					}
				}

				// remove all other functions
				if ( $node instanceof Comment || $node instanceof Doc ) {
					return NodeTraverser::REMOVE_NODE;
				}

				if ( $node instanceof FuncCall ) {
					$expr_name = $node->name->parts[0] ?? '';
					if ( empty( $expr_name ) ) {
						return $node;
					}

					// *static* functions defined in Common.php
					$common_functions = array_flip( [
						'nodeLength',
						'furthestAncestor',
						'isAncestorContainer',
						'nextNode',
						'previousNode',
						'nextNodeDescendants',
						'ownerDocument',
						'isAncestor',
						'isInclusiveAncestor',
						'isDescendant',
						'isInclusiveDescendant',
						'getPosition',
						'isContained',
						'isPartiallyContained',
						'indexOf',
						'myExtractContents',
						'myInsertNode',
						'isElement',
						'isText',
						'isDoctype',
						'ensurePreInsertionValidity',
						'assertNodesEqual',
						'getDomExceptionName',
						'rangeFromEndpoints',
					] );
					$harness_functions = array_flip( [
						'testNode',
						'create',
						'test',
						'async_test',
						'testRemove',
						'assert',
						'test_',
						'_test',
						'check',
						'runTestData',
						'done',
						'append',
						'step_func_done',
						'getIDs',
						'format_value',
						'attr_is',
						'getWin',
						'attributes_are',
						'getEnumerableOwnProps1',
						'getEnumerableOwnProps2',
						'getNonInsertableNodes',
						'doTest',
						'getNodeType',
						'insert',
						'lookupNamespaceURI',
						'preInsertionValidateHierarchy',
						'getNonParentNodes',
						'interfaceCheckMatches',
						'runSpecialMatchesTests',
						'runInvalidSelectorTestMatches',
						'runMatchesTest',
						'init',
						'isDefaultNamespace',
						'array_map',
						'generate_tests',
						// assertion methods
						'assert_array_equals',
						'assert_class_string',
						'assert_equals',
						'assert_false',
						'assert_idl_attribute',
						'assert_in_array',
						'assert_node',
						'assert_not_equals',
						'assert_readonly',
						'assert_throws_dom',
						'assert_throws_exactly',
						'assert_throws_js',
						'assert_true',
						'assert_unreached',
						// from Common.php
						'setupRangeTests',
						// helper functions defined in specific test classes
						'checkNodes',
						'check_iter', // aka checkIter
						'check_walker', // aka checkWalker
						'createHTMLDocuments',
						'createRangeWithUnparentedContainerOfSingleElement',
						'createSampleDOM',
						'moveNodeToNewlyCreatedDocumentWithAppendChild',
						'myCloneContents',
						'nestRangeInOuterContainer',
						'restoreIframe',
						'createXmlDoc',
						'parse',
						'serialize',
						'checkMetadata',
						// These are helper functions defined in classes
						// which unfortunately begin with 'test' and so
						// PHPUnit will think that they are standalone
						// test methods.  We should rename these.
						'testAlias',
						'testAttr',
						'testCloneContents',
						'testConstants',
						'testDeepEquality',
						'testDeleteContents',
						'testDoc',
						'testExtractContents',
						'testIsConnected',
						'testIterator',
						'testLeaf',
						'testLeafNode',
						'testMatched',
						'testNeverMatched',
						'testSetEnd',
						'testSetStart',
						'testSurroundContents',
						'testTree',
						'test_after', // aka testAfter
						'test_append', // aka testAppend
						'test_before', // aka testBefore
						'test_create', // aka testCreate
						'test_getElementsByTagNameNS', // aka testGetElementsByTagNameNS
						'test_prepend', // aka testPrepend
						'test_replaceWith', // aka testReplaceWith
					] );

					if ( array_key_exists( $expr_name, $common_functions ) ) {
						$args = $node->args;
						$call = new Node\Expr\StaticCall(
							new Node\Name( 'Common' ),
							$expr_name,
							$args,
							$node->getAttributes()
						);
						$this->uses['Common'] = true;
						$node = $call;
					} elseif ( array_key_exists( $expr_name, $harness_functions ) ) {
						$args = $node->args;

						if ( strpos( $expr_name, 'assert' ) === 0 ) {
							// TestCase already has methods named assert*
							// so add a 'wpt' prefix to these harness functions.
							$call = new Node\Expr\MethodCall(
								new Variable( 'this' ),
								$this->snakeToCamel( 'wpt_' . $expr_name ),
								$args,
								$node->getAttributes() );
						} else {
							$call = new Node\Expr\MethodCall( new Variable( 'this' ),
								$this->snakeToCamel( $expr_name ),
								$args,
								$node->getAttributes() );
						}

						$node = $call;
					}

					$replace_list = [ 'toASCIIUppercase' => 'mb_strtoupper',
						// or mb_convert_case MB_CASE_UPPER.
						'toASCIILowercase' => 'mb_strtolower' ];

					if ( preg_match( '(' . implode( '|', $replace_list ) . ')', $expr_name ) === 1 ) {
						$node->name->parts[0] = $replace_list[$expr_name];
					}

					return $node;
				}

				if ( $node instanceof Function_ ) {
					$remove = [ 'toASCIIUppercase', 'toASCIILowercase' ];
					$func_name = $node->name->name;

					if ( preg_match( '(' . implode( '|', $remove ) . ')', $func_name ) === 1 ) {
						return NodeTraverser::REMOVE_NODE;
					}

					$node = $this->factory->method( $this->snakeToCamel( $func_name ) )->makePublic()
						->addStmts( $node->stmts )->addParams( $node->getParams() )->getNode();

					return $node;
				}
			}
		};

		$traverser->addVisitor( $visitor );
		$stmts = $traverser->traverse( $stmts );

		if ( !$this->compact ) {
			// create test class
			if ( strpos( $this->test_name, 'Test' ) === false ) {
				$this->test_name .= 'Test';
			}

			// Extract the package name
			$pkg = implode(
				'\\',
				array_map(
					function ( $s ) {
						return $this->snakeToPascal( $s );
					},
					array_slice(
						explode( '/', $this->test_path ),
						3, -1
					)
				)
			);
			$class = $this->factory->class(
				$this->snakeToPascal( $this->test_name )
			)->extend(
				'WPTTestHarness'
			)->addStmts(
				$stmts
			)->setDocComment(
				'// @see ' . $this->test_path . '.'
			)->getNode();
			$use_stmts = $this->getUseStmts( $visitor->uses );
			$stmts = $this->factory->namespace(
				'Wikimedia\Dodo\Tests\WPT\\' . $pkg
			)->addStmts(
				$use_stmts
			)->addStmts( [
				$class
			] )->getNode();
		}

		$this->prettyPrint( $stmts );
	}

	/**
	 * @param array<string,bool> $extraUses
	 * @return array
	 */
	private function getUseStmts( $extraUses ) : array {
		$stmts = [];
		$list_ns = [
			'Node' => 'Wikimedia\Dodo\Node',
			'DocumentFragment' => 'Wikimedia\Dodo\DocumentFragment',
			'HTMLElement' => 'Wikimedia\Dodo\HTMLElement',
			'NodeFilter' => 'Wikimedia\Dodo\NodeFilter',
			'new Document' => 'Wikimedia\Dodo\Document',
			'instanceof Document' => 'Wikimedia\Dodo\Document',
			'XMLDocument' => 'Wikimedia\IDLeDOM\XMLDocument',
			'Element' => 'Wikimedia\Dodo\Element',
			'Attr' => 'Wikimedia\Dodo\Attr',
			'Comment' => 'Wikimedia\Dodo\Comment',
			'Text' => 'Wikimedia\Dodo\Text',
			'HTMLDivElement' => 'Wikimedia\Dodo\HTMLDivElement',
			'HTMLSpanElement' => 'Wikimedia\Dodo\HTMLSpanElement',
			'HTMLAnchorElement' => 'Wikimedia\Dodo\HTMLAnchorElement',
			'HTMLUnknownElement' => 'Wikimedia\Dodo\HTMLUnknownElement',
			'HTMLHtmlElement' => 'Wikimedia\Dodo\HTMLHtmlElement',
			'HTMLParagraphElement' => 'Wikimedia\Dodo\HTMLParagraphElement',
			'HTMLBodyElement' => 'Wikimedia\Dodo\HTMLBodyElement',
			'HTMLHeadElement' => 'Wikimedia\Dodo\HTMLHeadElement',
			'HTMLHRElement' => 'Wikimedia\Dodo\HTMLHRElement',
			'HTMLHeadingElement' => 'Wikimedia\Dodo\HTMLHeadingElement',
			'HTMLAppletElement' => 'Wikimedia\Dodo\HTMLAppletElement',
			'HTMLBRElement' => 'Wikimedia\Dodo\HTMLBRElement',
			'HTMLDListElement' => 'Wikimedia\Dodo\HTMLDListElement',
			'HTMLAreaElement' => 'Wikimedia\Dodo\HTMLAreaElement',
			'CharacterData' => 'Wikimedia\Dodo\CharacterData',
			'DocumentType' => 'Wikimedia\Dodo\DocumentType',
			'URL' => 'Wikimedia\Dodo\URL',
			'DOMException' => 'Wikimedia\Dodo\DOMException',
			'DOMImplementation' => 'Wikimedia\Dodo\DOMImplementation',
			'DOMParser' => 'Wikimedia\Dodo\DOMParser',
			'XMLSerializer' => 'Wikimedia\Dodo\XMLSerializer',
			'Range' => 'Wikimedia\Dodo\Range',
			'AbstractRange' => 'Wikimedia\Dodo\AbstractRange',
			'StaticRange' => 'Wikimedia\Dodo\StaticRange',
			'Common' => 'Wikimedia\Dodo\Tests\Harness\Utils\Common',
		];

		$notAdded = [];
		foreach ( $list_ns as $use => $namespace ) {
			if (
				strpos( $this->test, $use ) !== false ||
				( $extraUses[$use] ?? false ) !== false
			) {
				if ( $notAdded[$namespace] ?? true ) {
					$stmts[] = $this->factory->use( $namespace );
					$notAdded[$namespace] = false;
				}
			}
		}

		// harness namespace.
		$stmts[] = $this->factory->use(
			"Wikimedia\\Dodo\\Tests\\Harness\\" .
			$this->test_type . "TestHarness"
		);

		return $stmts;
	}

	/**
	 * Removes disparity after parsing.
	 */
	protected function postProcessWPTTest() : void {
		$find_replace = [ 'Node::prototype::insertBefore' =>
			'(new \ReflectionClass(Node::class))->hasMethod( "insertBefore" )',
			'Node::prototype::replaceChild' =>
				'(new \ReflectionClass(Node::class))->hasMethod( "replaceChild" )',
			'Object::getOwnPropertyNames' => '$this->getOwnPropertyNames',
			'$this->assertTrueData(isset($paragraphs[Symbol::iterator]));' =>
				'// $this->assertTrueData(isset($paragraphs[Symbol::iterator]));',
			'$this->assertTrueData(isset($elementClasses[Symbol::iterator]));' =>
				'// $this->assertTrueData(isset($elementClasses[Symbol::iterator]));',
			"gettype(DocumentFragment::prototype::getElementById), 'function'" =>
				"(new \ReflectionClass( DocumentFragment::class))->hasMethod( 'getElementById')",
			'testRemove' => 'assertTestRemove',
			'HTMLUnknownElement::prototype::isPrototypeOf( $deepClone )' =>
				'($deepClone instanceof HTMLUnknownElement)',
			'HTMLUnknownElement::prototype::isPrototypeOf( $clone )' =>
				'($clone instanceof HTMLUnknownElement)',
			'$doc::URL' => '$doc->URL',
			'\'type\' => $Element' => '\'type\' => Element::class',
			'\'type\' => $Text' => '\'type\' => Text::class',
			'\'type\' => $Comment' => '\'type\' => Comment::class',
			'testConstructor' => 'assertTestConstructor',
			'testCreate' => 'assertTestCreate',
			'Node::class::insertBefore' => '\'insertBefore\'',
			'$new_el[$pair[\'attr\']]' => '$new_el->{$pair[\'attr\']}',
			'[$method]' => '->{$method}',
			// 'new Range()' => '$this->doc->createRange()',
			'Number(' => 'intval(',
			// workaround reference to uninitialized array
			'$testRangesCached = [];' =>
				'$testRangesCached = array_pad([], count($this->getCommon()->testRanges), null);',
			# keep testRangesCached from being matched by the $testRanges
			# replacement (since strtr will use the longest match)
			'$testRangesCached' => '$testRangesCached',
		];

		// convert $_x to $this->_x.
		$convert_list = $this->convertVarToClassVar( [
			'$i2',
			'$i1',
			'$parentListbox2',
			'$parentListbox',
			'$blankIdParent',
			'$lightParagraph',
			'$shadowHost',
			'$invalid_names',
			'$invalid_qnames',
			'$valid_names',
			'$validSelectors',
			'$invalidSelectors',
			'$window',
			'$outerShadowHost',
			'$outerShadowHost',
		] );
		// convert $_x to $this->_x.
		$convert_list = array_merge( $this->convertVarToCommonVar( [
			'$testNodes',
			'$testNodesShort',
			'$testRanges',
			'$testRangesShort',
			'$testDiv',
		] ), $convert_list );

		$find_replace = array_merge( $find_replace, $convert_list );

		$this->test = strtr( $this->test, $find_replace );

		// Now fix up some unnecessary use statements
		do {
			$this->test = preg_replace_callback(
				'/(use[(][^)]*)' .
				preg_quote( '&$this->getCommon()->', '/' ) .
				'[A-Za-z]+(, |\))/D',
				static function ( $matches ) {
					if ( $matches[2] === ')' ) {
						if ( $matches[1] === 'use(' ) {
							return ''; // No more use variables!
						}
						// trim the ", " off the end of $matches[1]
						return substr( $matches[1], 0, -2 ) . $matches[2];
					}
					return $matches[1];
				},
				$this->test, -1, $count
			);
		} while ( $count > 0 ); // repeat as necessary

		// Remove unnecessary empty lines.
		$this->test = preg_replace( '/^[ \t]*[\r\n]+/m', '', $this->test );

		// Replace constructs like $object = Object::create($collection) with clone.
		preg_match( '/Object::create\((.*)\)/', $this->test, $matches );
		if ( isset( $matches[1] ) ) {
			$this->test = preg_replace( '/Object::create\((.*)\)/',
				'clone ' . $matches[1],
				$this->test );
		}

		// Replace constructs like String($span->classList).
		preg_replace( '/String\((.*)\)/', '$this->toString($1)', $this->test );
	}

	/**
	 * Marks the test as skipped.
	 *
	 * @return Node\Expr\MethodCall
	 */
	protected function markTestAsSkipped() : Node\Expr\MethodCall {
		return new Node\Expr\MethodCall( new Variable( 'this' ), 'markTestSkipped' );
	}

	/**
	 *
	 * @param array $ast
	 * @param string $name
	 *
	 * @return null|Node
	 */
	protected function findFuncCall( array $ast, string $name ) : ?Node {
		return $this->finder->findFirst( $ast,
			static function ( $node ) use ( $name ) {
				if ( isset( $node->expr ) && isset( $node->expr->name ) && $node->expr instanceof FuncCall ) {
					$expr_name = $node->expr->name->toString();
					if ( $expr_name == $name ) {
						return $node;
					}
				}
			} );
	}

	/**
	 * @param string $code
	 *
	 * @return string
	 */
	protected function makeFuncPublic( string $code ) : string {
		return str_replace( 'function', "public function", $code );
	}

	/**
	 * @param array|Node $ast
	 *
	 * @return string
	 */
	protected function dumpAst( $ast ) : string {
		$dumper = new NodeDumper;
		return $dumper->dump( $ast ) . "n";
	}

	/**
	 * Preprocess W3C test
	 */
	private function preProcessW3CTest() : void {
		$find_replace = [
			'global $builder;' => '',
			'$builder = null;' => '$builder = $this->getBuilder();',
			'Exception $ex' => 'DOMException $ex',
			// 'throw $ex;' => '$this->fail($ex->getMessage());',
			'&& false' => '',
			'$doc->open()' => '// $doc->open()',
			'$doc->close()' => '// $doc->close()',
			'count( $childValue )' => 'strlen($childValue)',
			'toLowerArray(' => 'array_map(\'strtolower\',', ];

		$this->test = strtr( $this->test, $find_replace );
	}

	/**
	 * @param array $ast
	 *
	 * @return Node[]|Node\Stmt\ClassMethod[]
	 */
	private function prepareAst( array $ast ) {
		$main_method = $this->snakeToCamel( 'test ' . $this->test_name );

		$functions = $this->finder->find( $ast, static function ( $node ) {
			return $node instanceof Function_;
		} );

		$ast = array_filter( $ast,
			static function ( $smtm ) {
				if ( !$smtm instanceof Function_ ) {
					return $smtm;
				}

				return null;
			} );

		$additional_stmts = [];

		if ( $this->test_type === TestsGenerator::WPT ) {
			$source_file = $this->parser->parse( '<?php $this->doc = $this->loadHtmlFile (\'' . $this->test_path .
				'\');' );
			$additional_stmts[] = reset( $source_file );
			$additional_elements = [ 'i1',
				'i2',
				'parentListbox',
				'parentListbox2',
				'blankIdParent',
				'lightParagraph',
				'shadowHost' ];

			foreach ( $additional_elements as $el ) {
				if ( strpos( $this->test, '$' . $el ) !== false ) {
					$_lb = $this->parser->parse( sprintf( '<?php $this->%1$s = $this->doc->getElementById("%1$s");',
						$el ) );
					$additional_stmts[] = reset( $_lb );
				}
			}
		}

		$traverser = new NodeTraverser;
		// removes nested functions.
		$traverser->addVisitor( new class() extends NodeVisitorAbstract {
			/**
			 * @param mixed $node
			 *
			 * @return int
			 */
			public function leaveNode( $node ) {
				if ( $node instanceof Function_ ) {
					return NodeTraverser::REMOVE_NODE;
				}
			}
		} );

		foreach ( $functions as $function ) {
			$function->stmts = $traverser->traverse( $function->stmts );
		}

		$ast = $traverser->traverse( $ast );

		if ( $this->test_type === TestsGenerator::W3C ) {
			$stmts = array_filter( $functions, function ( &$node ) use ( $ast ) {
				if ( $node->name->name === $this->test_name ) {
					$node->stmts = array_merge( $ast, $node->stmts );
					return $node;
				}

				return false;
			} );
		} else {
			$node = $this->factory->method( $main_method )->makePublic()->addStmts(
				$additional_stmts )
				->addStmts( $ast )->getNode();

			$stmts = array_merge( $functions, [ $node ] );
		}

		if ( in_array( $main_method,
			[ 'testAppendOnDocument',
				'testPrependOnDocument' ] ) ) {
			if ( isset( $functions[0] ) ) {
				$functions[0]->stmts = array_merge( $additional_stmts, $functions[0]->stmts );
			}

			$stmts = $functions;
		}

		return $stmts;
	}
}
