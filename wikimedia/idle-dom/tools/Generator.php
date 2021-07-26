<?php

namespace Wikimedia\IDLeDOM\Tools;

use Wikimedia\Assert\Assert;
use Wikimedia\WebIDL\WebIDL;

class Generator {
	/** @var array */
	private $ast;
	/** @var array */
	private $options;
	/** @var array */
	private $mixins = [];
	/** @var array */
	private $defs = [];
	/** @var array */
	private $nameMap = [];
	/** @var array */
	private $typedefs = [];

	private const RESERVED_NAMES = [
		// PHP keywords and compile-time constants.
		// As of PHP 7.0.0, all keywords other than `class` are allowed
		// as property, constant, and method names of classes, interfaces
		// and traits.
		// All compile-time constants start with `__`, which is separately
		// reserved as a prefix.
		// https://www.php.net/manual/en/reserved.keywords.php
		'class',
		// Predefined constants:
		// https://www.php.net/manual/en/reserved.constants.php
		'PHP_VERSION',
		'PHP_MAJOR_VERSION',
		'PHP_MINOR_VERSION',
		'PHP_RELEASE_VERSION',
		'PHP_VERSION_ID',
		'PHP_EXTRA_VERSION',
		'PHP_ZTS',
		'PHP_DEBUG',
		'PHP_MAXPATHLEN',
		'PHP_OS',
		'PHP_OS_FAMILY',
		'PHP_SAPI',
		'PHP_EOL',
		'PHP_INT_MAX',
		'PHP_INT_MIN',
		'PHP_INT_SIZE',
		'PHP_FLOAT_DIG',
		'PHP_FLOAT_EPSILON',
		'PHP_FLOAT_MIN',
		'PHP_FLOAT_MAX',
		'DEFAULT_INCLUDE_PATH',
		'PEAR_INSTALL_DIR',
		'PEAR_EXTENSION_DIR',
		'PHP_EXTENSION_DIR',
		'PHP_PREFIX',
		'PHP_BINDIR',
		'PHP_BINARY',
		'PHP_MANDIR',
		'PHP_LIBDIR',
		'PHP_DATADIR',
		'PHP_SYSCONFDIR',
		'PHP_LOCALSTATEDIR',
		'PHP_CONFIG_FILE_PATH',
		'PHP_CONFIG_FILE_SCAN_DIR',
		'PHP_SHLIB_SUFFIX',
		'PHP_FD_SETSIZE',
		'E_ERROR',
		'E_WARNING',
		'E_PARSE',
		'E_NOTICE',
		'E_CORE_ERROR',
		'E_CORE_WARNING',
		'E_COMPILE_ERROR',
		'E_COMPILE_WARNING',
		'E_USER_ERROR',
		'E_USER_WARNING',
		'E_USER_NOTICE',
		'E_RECOVERABLE_ERROR',
		'E_DEPRECATED',
		'E_USER_DEPRECATED',
		'E_ALL',
		'E_STRICT',
		'__COMPILER_HALT_OFFSET__',
		'true',
		'false',
		'null',
		'PHP_WINDOWS_EVENT_CTRL_C',
		'PHP_WINDOWS_EVENT_CTRL_BREAK',
		// Other reserved words
		// https://www.php.net/manual/en/reserved.other-reserved-words.php
		'int', 'float', 'bool', 'string', 'true', 'false', 'null',
		'void', 'iterable', 'object', 'resource', 'mixed', 'numeric',
	];

	/**
	 * Construct a new generator.
	 * @param array $ast
	 * @param array $options
	 */
	private function __construct( array $ast, array $options = [] ) {
		$this->ast = $ast;
		$this->options = $options;
		foreach ( $ast as &$definition ) {
			// Optionally remove legacy members
			if ( $this->options['skipLegacy'] ?? false ) {
				$definition['members'] = array_values( array_filter(
					$definition['members'] ?? [],
					static function ( $m ) {
						foreach ( $m['trailingComments'] ?? [] as $c ) {
							if ( preg_match( '|^// legacy|', $c ) ) {
								// Skip this legacy member
								return false;
							}
						}
						return true;
					} ) );
			}
			if ( $definition['type'] === 'includes' ) {
				// Collect mixins
				$this->mixins[$definition['target']][] = $definition['includes'];
			} elseif ( $definition['type'] === 'typedef' ) {
				// Collect typedefs
				$name = $definition['name'];
				Assert::invariant(
					!array_key_exists( $name, $this->typedefs ),
					"Duplicate definition for $name"
				);
				$this->typedefs[$name] = $definition['idlType'];
			} else {
				$name = $definition['name'];
				if ( $definition['partial'] ?? false ) {
					Assert::invariant(
						array_key_exists( $name, $this->defs ),
						"Partial definition is missing main definition of $name"
					);
					foreach ( $definition["members"] ?? [] as $m ) {
						$this->defs[$name]["members"][] = $m;
					}
				} else {
					Assert::invariant(
						!array_key_exists( $name, $this->defs ),
						"Duplicate definition for $name"
					);
					$this->defs[$name] = $definition;
				}
			}
		}
		unset( $definition );
		// Sort definitions so that name resolution is deterministic
		ksort( $this->defs );
		foreach ( $this->defs as $name => $def ) {
			if ( array_key_exists( $name, $this->mixins ) ) {
				sort( $this->mixins[$name] ); // sort mixins
			}
			// Direct inheritance always has precedence
			if ( ( $def['inheritance'] ?? null ) !== null ) {
				if ( !array_key_exists( $name, $this->mixins ) ) {
					$this->mixins[$name] = [];
				}
				array_unshift( $this->mixins[$name], $def['inheritance'] );
			}
		}
		// Resolve name conflicts
		$done = [];
		foreach ( $this->defs as $name => &$def ) {
			$this->resolveNames( $def, $done );
		}
	}

	private function resolveNames( array &$def, array &$done ): array {
		$topName = $def['name'];
		$allNames = [];
		// Ensure each definition is only resolved once
		if ( array_key_exists( $topName, $done ) ) {
			return $done[$topName];
		}
		$done[$topName] = true; // will overwrite this
		// Resolve the names in this class
		$used = [];
		foreach ( self::RESERVED_NAMES as $n ) {
			$used[$n] = true;
		}
		$findName = static function ( $n, $builtin = false ) use ( &$used, &$allNames ) {
			$origName = $n;
			if ( $used[$n] ?? false ) {
				for ( $i = 1; ; $i++ ) {
					$n2 = "idl" . str_repeat( '_', $i ) . $n;
					if ( !( $used[$n2] ?? false ) ) {
						$n = $n2;
						break;
					}
				}
			}
			Assert::invariant( !( $used[$n] ?? false ), "$n should be unused" );
			$used[$n] = true;
			$allNames[] = $n;
			if ( $builtin ) {
				Assert::invariant(
					$n === $origName,
					"Name conflict for built-in method $origName"
				);
			}
			return $n;
		};
		// If there are mixins, resolve names in the mixins first
		foreach ( $this->mixins[$topName] ?? [] as $m ) {
			Assert::invariant( array_key_exists( $m, $this->defs ),
							  "Missing definition of $m in $topName" );
			foreach ( $this->resolveNames( $this->defs[$m], $done ) as $mname ) {
				$r = $findName( $mname );
				Assert::invariant( $r == $mname, "Mixins shouldn't conflict" );
			}
		}
		// Move on to members of this type.
		if ( $def['type'] === 'dictionary' &&
			 ( $def['inheritance'] ?? null ) === null
		) {
			// *Top level* dictionaries implement ArrayAccess; reserve those
			// names. (Subclasses get it from the parent.)
			$aa = [ 'offsetExists','offsetGet','offsetSet','offsetUnset' ];
			foreach ( $aa as $name ) {
				$this->nameMap["$topName:op:_$name"] = $findName( $name, true );
			}
		}
		if ( $def['type'] === 'callback' ) {
			// Callbacks have synthetic 'invoke' and 'cast' methods.
			$this->nameMap["$topName:op:_invoke"] = $findName( "invoke" );
			$this->nameMap["$topName:op:_cast"] = $findName( "cast" );
			$done[$topName] = $allNames;
			return $allNames;
		}
		if ( $def['type'] === 'enum' ) {
			// Enumerations have a synthetic 'cast' method.
			$this->nameMap["$topName:op:_cast"] = $findName( "cast" );
			// Treat enumerations like interfaces with const members
			foreach ( $def['values'] as $m ) {
				$name = preg_replace( '/[^A-Za-z0-9_]/', '_', $m['value'] );
				$this->nameMap["$topName:const:$name"] = $findName( $name );
			}
			$done[$topName] = $allNames;
			return $allNames;
		}
		Assert::invariant(
			array_key_exists( 'members', $def ),
			"$topName doesn't have members!"
		);

		foreach ( $def['members'] as &$m ) {
			// Reserve `getIterator` for iterables
			if ( $m['type'] === 'iterable' ) {
				$this->nameMap["$topName:op:_iterable"] = $findName( 'getIterator', true );
			}
			// Reserve `count` for [PHPCountable]
			if ( self::extAttrsContain( $m, 'PHPCountable' ) ) {
				$this->nameMap["$topName:op:_count"] = $findName( 'count', true );
			}
			// Reserve names for 'unnamed' getter/setter/deleters
			if (
				$m['type'] === 'operation' &&
				( $m['name'] ?? '' ) === '' &&
				( $m['special'] ?? '' ) !== '' ) {
				if ( $m['special'] === 'stringifier' ) {
					// unnamed stringifier operation
					$which = "stringifier";
					$m['idlType'] = [
						'idlType' => 'DOMString',
						'type' => null,
						'extAttrs' => [],
						'generic' => '',
						'nullable' => false,
						'union' => false,
					];
				} elseif ( count( $m['arguments'] ?? [] ) === 0 ) {
					continue;
				} else {
					$firstArgType = $m['arguments'][0]['idlType']['idlType'];
					$which = ( (
						$firstArgType === "unsigned long"
					) ? "indexed " : "named " ) . ( $m['special'] ?? '' );
				}
				$names = [
					"indexed getter" => "item",
					"named getter" => "namedItem",
					"indexed setter" => "setItem",
					"named setter" => "setNamedItem",
					"indexed deleter" => "removeItem",
					"named deleter" => "removeNamedItem",
					"stringifier" => "toString",
				];
				$m['name'] = $names[$which] ?? '';
			}
		}
		unset( $m );

		// First resolve constants
		foreach ( $def['members'] as $m ) {
			if ( $m['type'] === 'const' ) {
				$name = $m['name'];
				$this->nameMap["$topName:const:$name"] = $findName( $name );
			}
		}
		// Dictionaries and callback interfaces have a special 'cast' method
		// (But only top-level; children inherit from parent.)
		if ( $def['type'] === 'dictionary' ||
			 $def['type'] === 'callback' ||
			 $def['type'] === 'callback interface' ) {
			if ( ( $def['inheritance'] ?? null ) === null ) {
				$this->nameMap["$topName:op:_cast"] = $findName( "cast" );
			}
		}
		// Then attribute getters/setters (including dictionary getters/setters)
		foreach ( $def['members'] as $m ) {
			if ( $m['type'] === 'attribute' || $m['type'] === 'field' ) {
				$name = $m['name'];
				$this->nameMap["$topName:get:$name"] =
					$findName( 'get' . ucfirst( $name ) );
				$this->nameMap["$topName:set:$name"] =
					$findName( 'set' . ucfirst( $name ) );
			}
		}
		// Then operations
		foreach ( $def['members'] as $m ) {
			if ( $m['type'] === 'operation' ) {
				$name = $m['name'];
				$this->nameMap["$topName:op:$name"] =
					$findName( $name );
			}
		}
		$done[$topName] = $allNames;
		return $allNames;
	}

	/**
	 * Returns the conflict-resolved PHP name of a member.
	 * @param string $topName
	 * @param string $type
	 * @param string $name
	 * @return string
	 */
	public function map( string $topName, string $type, string $name ): string {
		return $this->nameMap[ "$topName:$type:$name" ];
	}

	/**
	 * Return the set of mixins for an interface.
	 * @param string $topName
	 * @return array
	 */
	public function mixins( string $topName ): array {
		return $this->mixins[ $topName ] ?? [];
	}

	/**
	 * Look up a definition.
	 * @param string $topName
	 * @return ?array
	 */
	public function def( string $topName ): ?array {
		return $this->defs[ $topName ] ?? null;
	}

	/**
	 * Look up a typedef.
	 * @param string $topName
	 * @return ?array
	 */
	public function typedef( string $topName ): ?array {
		return $this->typedefs[ $topName ] ?? null;
	}

	/**
	 * Look up a member.
	 * @param string $topName
	 * @param string $memberName
	 * @return ?array
	 */
	public function memberDef( string $topName, string $memberName ): ?array {
		$def = $this->def( $topName );
		if ( $def !== null ) {
			foreach ( $def['members'] as $m ) {
				if ( ( $m['name'] ?? null ) === $memberName ) {
					return $m;
				}
			}
		}
		return null;
	}

	/**
	 * Search the extended attributes for the given member for an attribute
	 * with the given name.
	 * @param array $m WebIDL AST for a member
	 * @param string $name Name of the extended attribute to search for
	 * @return bool True if an extended attribute with the given name is
	 *   among the extended attributes of the member.
	 */
	public static function extAttrsContain( array $m, string $name ) : bool {
		return self::extAttrNamed( $m, $name ) !== null;
	}

	/**
	 * Search the extended attributes for the given member for an attribute
	 * with the given name and return it.
	 * @param array $m WebIDL AST for a member
	 * @param string $name Name of the extended attribute to search for
	 * @return array|null The extended attribute array, if an extended
	 *   attribute with the given name is among the extended attributes of
	 *   the member, otherwise `null`.
	 */
	public static function extAttrNamed( array $m, string $name ) : ?array {
		foreach ( $m['extAttrs'] ?? [] as $ea ) {
			if ( ( $ea['name'] ?? '' ) === $name ) {
				return $ea;
			}
		}
		return null;
	}

	private function typeIncludes( array $ty, string $which ) {
		if ( $ty['union'] ?? false ) {
			foreach ( $ty['idlType'] as $subtype ) {
				if ( $this->typeIncludes( $subtype, $which ) ) {
					return true;
				}
			}
			return false;
		}
		$generic = $ty['generic'] ?? '';
		if ( $generic !== '' ) {
			return false;
		}
		if ( array_key_exists( $ty['idlType'], $this->typedefs ) ) {
			return $this->typeIncludes(
				$this->typedefs[$ty['idlType']], $which
			);
		}
		if ( !array_key_exists( $ty['idlType'], $this->defs ) ) {
			return false;
		}
		$d = $this->defs[$ty['idlType']] ?? null;
		return $d && $d['type'] === $which;
	}

	/**
	 * Return the 'phan compatible' version of a type, for use in PHP doc.
	 * @param array $ty The WebIDL AST type
	 * @param array $opts Whether this is a return type, etc.
	 * @return string
	 */
	public function typeToPHPDoc( array $ty, array $opts = [] ):string {
		return $this->typeToPHP( $ty, [
			'phpdoc' => true,
		] + $opts );
	}

	/**
	 * Return the 'PHP' version of a type, for use in type hints.
	 * @param array $ty The WebIDL AST type
	 * @param array $opts Whether this is a return type, etc.
	 * @return string
	 */
	public function typeToPHP( array $ty, array $opts = [] ):string {
		$phpdoc = $opts['phpdoc'] ?? false;
		if ( ( $opts['returnType'] ?? false ) &&
			 !( $opts['returnType2'] ?? false ) &&
			 !$phpdoc ) {
			$result = $this->typeToPHP( $ty, [ 'returnType2' => true ] + $opts );
			if ( self::extAttrsContain( $ty, 'PHPNoHint' ) ) {
				// suppress the PHP type hint
				return '';
			}
			if ( substr( $result, 0, 2 ) === '/*' && substr( $result, -2 ) === '*/' ) {
				return '';
			} else {
				return " : $result";
			}
		}
		if (
			( $opts['setter'] ?? false ) &&
			self::extAttrsContain( $ty, 'LegacyNullToEmptyString' )
		) {
			$ty['nullable'] = true;
		}

		$n = ( $ty['nullable'] ?? false ) ? '?' : '';
		if ( $ty['union'] ?? false ) {
			if ( !$phpdoc ) {
				return "/* {$n}mixed */";
			}
			$result = implode( '|', array_map( function ( $ty ) use ( $opts ) {
				return $this->typeToPHPDoc( $ty, $opts );
			}, $ty['idlType'] ) );
			if ( $ty['nullable'] ?? false ) {
				$result .= '|null';
			}
			return $result;
		}
		$generic = $ty['generic'] ?? '';
		switch ( $generic ) {
		case 'sequence':
			if ( !$phpdoc ) {
				return 'array';
			}
			return $n . 'list<' . $this->typeToPHPDoc( $ty['idlType'][0], $opts ) . '>';
		case '':
			break;
		default:
			self::unreachable( "Unknown generic type: $generic" );
		}

		if ( array_key_exists( $ty['idlType'], $this->typedefs ) ) {
			return $this->typeToPHP( $this->typedefs[$ty['idlType']], $opts );
		}
		if ( array_key_exists( $ty['idlType'], $this->defs ) ) {
			// An object type
			$result = $ty['idlType'];
			if ( $result === ( $opts['topName'] ?? null ) ) {
				'@phan-var string $result'; // @var string $result
				$namespace = $opts['namespace'] ?? '\Wikimedia\IDLeDOM';
				$result = "$namespace\\$result";
			}
			$extraType = null;
			if ( $this->typeIncludes( $ty, 'enum' ) ) {
				$result = 'string'; // enumerations are strings
				$result = $n . $result;
				if ( !$phpdoc ) {
					$c = ( $opts['flagEnums'] ?? false ) ? 'enum' : $ty['idlType'];
					$result = "/* $c */ $result";
				}
				return $result;
			}
			if ( $this->typeIncludes( $ty, 'dictionary' ) ) {
				$extraType = 'associative-array';
			}
			if ( $this->typeIncludes( $ty, 'callback' ) ||
				 $this->typeIncludes( $ty, 'callback interface' ) ) {
				$extraType = 'callable';
			}
			if ( $extraType ) {
				if ( !$phpdoc ) {
					return "/* {$n}mixed */";
				}
				return "$result|$extraType" . ( $n === '' ? '' : '|null' );
			}
			if ( !$phpdoc ) {
				// PHP doesn't support full contravariance and covariance
				// of interfaces until 7.4 -- and even when it does strict
				// runtime typing makes incremental adoption of IDLeDOM
				// interfaces hard.  So avoid strict PHP type hints for
				// DOM types.
				return "/* $n$result */";
			} elseif ( $n === '?' ) {
				// work around phpcs bug which flags parameters w/ defaults
				// but w/o explicit PHP type hint as violations of
				// MediaWiki.Commenting.FunctionComment.PHP71NullableDocOptionalArg
				return "$result|null";
			}
			return "$n$result";
		}
		switch ( $ty['idlType'] ) {
		case 'any':
			if ( !$phpdoc ) {
				return '/* any */';
			}
			return 'mixed|null';
		case 'void':
			self::unreachable( "void is now 'undefined'" );
			// This could fall through if we weren't asserting
		case 'undefined':
			if ( $opts['returnType'] ?? false ) {
				return 'void';
			}
			if ( $phpdoc ) {
				return 'null';
			}
			return '/* undefined */'; // bail
		case 'boolean':
			return $n . 'bool';
		case 'octet':
		case 'short':
		case 'unsigned short':
		case 'long':
		case 'unsigned long':
			return $n . 'int';
		case 'float':
		case 'double':
		case 'unrestricted double':
			return $n . 'float';
		case 'DOMString':
		case 'USVString':
		case 'CSSOMString':
			return $n . 'string';
		default:
			self::unreachable( "Unknown type " . var_export( $ty, true ) );
		}
	}

	/**
	 * Return the 'PHP' version of a value, for use in defaults/constants/etc.
	 * @param array $val The WebIDL AST value
	 * @param array $opts Options that may affect the conversion
	 * @return string
	 */
	public function valueToPHP( array $val, array $opts = [] ):string {
		switch ( $val['type'] ) {
		case 'number':
			return $val['value'];
		case 'boolean':
			return $val['value'] ? 'true' : 'false';
		case 'null':
			return 'null';
		case 'string':
			return var_export( $val['value'], true );
		default:
			self::unreachable( "Unknown value type " . var_export( $val, true ) );
		}
		return '<broken>';
	}

	/**
	 * Write out the generated interfaces to the given directory.
	 * @param string $dir
	 */
	public function write( string $dir ): void {
		foreach ( $this->defs as $name => $def ) {
			// Interface
			$filename = $dir . "/$name.php";
			$out = InterfaceBuilder::emit( $this, $def, $this->options );
			if ( $out !== null ) {
				file_put_contents( $filename, $out );
			}
			// Helper Traits
			$filename = $dir . "/Helper/$name.php";
			$out = HelperBuilder::emit( $this, $def, $this->options );
			if ( $out !== null ) {
				file_put_contents( $filename, $out );
			}
			// Stubs
			$filename = $dir . "/Stub/$name.php";
			$out = StubBuilder::emit( $this, $def, $this->options );
			if ( $out !== null ) {
				file_put_contents( $filename, $out );
			}
		}
	}

	/**
	 * This is a workaround while we wait for wikimedia/assert 0.5.1 to
	 * be released.
	 * @param string $reason
	 */
	private static function unreachable( string $reason = "should never happen" ) {
		// @phan-suppress-next-line PhanImpossibleCondition
		Assert::invariant( false, $reason );
	}

	/** Main entry point: generates DOM interfaces from WebIDL */
	public static function main() {
		$webidl = [];
		$files = [
			"DOM", "misc", "HTML", "HTMLDocument", "parsing", "php",
			"URL", "CSS", "CSSProperties",
		];
		foreach ( $files as $f ) {
			$filename = __DIR__ . "/../spec/$f.webidl";
			$webidl[] = file_get_contents( $filename );
		}
		$idl = WebIDL::parse( implode( "\n", $webidl ), [
			'keepComments' => true
		] );
		$gen = new Generator( $idl, [
			'skipLegacy' => false,
		] );
		// Write interfaces
		$gen->write( __DIR__ . '/../src' );
	}
}
