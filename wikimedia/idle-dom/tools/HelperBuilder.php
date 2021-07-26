<?php

namespace Wikimedia\IDLeDOM\Tools;

use Wikimedia\Assert\Assert;

/**
 * This class builds PHP traits which can be used to ease the implementation
 * of the DOM interface classes.  DOM implementations aren't under any
 * obligation to use these traits.
 */
class HelperBuilder extends Builder {
	/**
	 * Helper method: generates a typical trait start.
	 * @param string $topName The class name
	 */
	protected function firstLine( string $topName ): void {
		$this->e->phpPrologue( 'Wikimedia\IDLeDOM\Helper' );

		$this->e->emitMarker( 'UseStatements' );
		$this->nl();
		$this->nl( "trait $topName {" );
		$this->nl();
		$this->nl( '// Underscore is used to avoid conflicts with DOM-reserved names' );
		$this->nl( '// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore' );
		$this->nl( '// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName' );
		$this->nl();
		$this->nl( '/**' );
		$this->nl( ' * Handle an attempt to get a non-existing property on this' );
		$this->nl( ' * object.  The default implementation raises an exception' );
		$this->nl( ' * but the implementor can choose a different behavior:' );
		$this->nl( ' * return null (like JavaScript), dynamically create the' );
		$this->nl( ' * property, etc.' );
		$this->nl( ' * @param string $prop the name of the property requested' );
		$this->nl( ' * @return mixed' );
		$this->nl( ' */' );
		// This function is implemented in base classes, but given as an
		// abstract method in subclasses so as not to override any
		// implementation present in a superclass.
		$def = $this->gen->def( $topName );
		if ( ( $def['inheritance'] ?? null ) === null ) {
			$this->nl( 'protected function _getMissingProp( string $prop ) {' );
			$this->triggerError( '__get', 'prop', 1 );
			$this->nl( 'return null;' ); // to keep linter happy
			$this->nl( '}' );
			$this->skip = false;
		} else {
			$this->nl( 'abstract protected function _getMissingProp( string $prop );' );
		}
		$this->nl();
		$this->nl( '/**' );
		$this->nl( ' * Handle an attempt to set a non-existing property on this' );
		$this->nl( ' * object.  The default implementation raises an exception' );
		$this->nl( ' * but the implementor can choose a different behavior:' );
		$this->nl( ' * ignore the operation (like JavaScript), dynamically create' );
		$this->nl( ' * the property, etc.' );
		$this->nl( ' * @param string $prop the name of the property requested' );
		$this->nl( ' * @param mixed $value the value to set' );
		$this->nl( ' */' );
		if ( ( $def['inheritance'] ?? null ) === null ) {
			$this->nl( 'protected function _setMissingProp( string $prop, $value ) : void {' );
			$this->triggerError( '__set', 'prop', 1 );
			$this->nl( '}' );
			$this->skip = false;
		} else {
			$this->nl( 'abstract protected function _setMissingProp( string $prop, $value ) : void;' );
		}
		$this->nl();

		$this->nl( '// phpcs:enable' );
		$this->nl();
	}

	/**
	 * Return the top-most parent definition, which is the one that
	 * will define the cast/ArrayAccess/etc methods.
	 * @param string $topName Name of this definition
	 * @return string Name of the topmost parent
	 */
	private function parentName( string $topName ): string {
		$parentDef = $this->gen->def( $topName );
		while ( ( $parentDef['inheritance'] ?? null ) !== null ) {
			'@phan-var array $parentDef'; /** @var array $parentDef */
			$parentDef = $this->gen->def( $parentDef['inheritance'] );
		}
		'@phan-var array $parentDef'; /** @var array $parentDef */
		return $parentDef['name'];
	}

	private function callbackHelper( string $topName, string $name, array $m ): void {
		$typeOpts = [ 'topName' => $topName ];
		$parentName = $this->parentName( $topName );
		Assert::invariant(
			( $m['special'] ?? '' ) === '', "Special callback method?"
		);
		foreach ( $m['arguments'] as $a ) {
			Assert::invariant(
				!( $a['variadic'] ?? false ), "Variadic not yet implemented"
			);
		}
		$r = InterfaceBuilder::memberOperationHelper( $this->gen, $topName, $name, $m );
		// Record types used, so we can generate proper import statements
		$this->use( $m['idlType'], $typeOpts );
		foreach ( $m['arguments'] as $a ) {
			$this->use( $a['idlType'], $typeOpts );
		}

		$this->nl( '/**' );
		$this->nl( ' * Make this callback interface callable.' );
		$this->nl( ' * @param mixed ...$args' );
		$this->nl( " * @return {$r['retTypeDoc']}" );
		$this->nl( ' */' );
		$this->nl( 'public function __invoke( ...$args ) {' );
		$this->emitThisHint( $topName );
		$this->nl( "{$r['return']}\$this->{$r['funcName']}({$r['invokeArgs']});" );
		$this->nl( '}' );
		$this->nl();

		$this->nl( '/**' );
		$this->nl( " * Create a $topName from a callable." );
		$this->nl( ' *' );
		$this->nl( " * @param callable|\\Wikimedia\\IDLeDOM\\$topName \$f" );
		$this->nl( " * @return \\Wikimedia\\IDLeDOM\\$topName" );
		$this->nl( ' */' );
		$cast = $this->map( $parentName, 'op', '_cast' );
		$this->nl( "public static function $cast( \$f ) {" );
		$this->nl( "if ( \$f instanceof \\Wikimedia\\IDLeDOM\\$topName ) {" );
		$this->nl( 'return $f;' );
		$this->nl( '}' );
		$this->nl( "return new class( \$f ) implements \\Wikimedia\\IDLeDOM\\$topName {" );
		$this->nl( "use $topName;" );
		$this->nl();
		$this->nl( '/** @var callable */' );
		$this->nl( 'private $f;' );
		$this->nl();
		$this->nl( '/**' );
		$this->nl( ' * @param callable $f' );
		$this->nl( ' */' );
		$this->nl( 'public function __construct( $f ) {' );
		$this->nl( '$this->f = $f;' );
		$this->nl( '}' );
		$this->nl();
		$this->nl( '/**' );
		foreach ( $r['paramDocs'] as $a ) {
			$this->nl( " * @param $a" );
		}
		$this->nl( " * @return {$r['retTypeDoc']}" );
		$this->nl( ' */' );
		$this->nl( "public function {$r['funcName']}({$r['phpArgs']}){$r['retType']} {" );
		$this->nl( '$f = $this->f;' );
		$this->nl( "{$r['return']}\$f({$r['castArgs']});" );
		$this->nl( '}' );
		$this->nl( '};' );
		$this->nl( '}' );
	}

	/** @inheritDoc */
	protected function emitCallback( string $topName, array $def ):void {
		$this->firstLine( $topName );
		$this->callbackHelper( $topName, '_invoke', $def );
		$this->nl( '}' );
	}

	/** @inheritDoc */
	protected function emitCallbackInterface( string $topName, array $def ):void {
		// Find the callback method
		$m = null;
		foreach ( $def['members'] as $mm ) {
			if ( $mm['type'] === 'operation' ) {
				Assert::invariant( $m === null, "Too many operations!" );
				$m = $mm;
			}
		}
		Assert::invariant( $m !== null, "Couldn't find operation!" );

		$this->firstLine( $topName );
		$this->callbackHelper( $topName, $m['name'], $m );
		$this->nl( '}' );
	}

	/**
	 * Process members and extract attribute information.
	 * @param Generator $gen
	 * @param string $topName
	 * @param array $typeOpts
	 * @param array &$attrs
	 * @param bool $singleInheritance Whether the class $topName is on the
	 *   single-inheritance chain or not.
	 */
	public static function collectAttributes(
		Generator $gen, string $topName, array $typeOpts, array &$attrs,
		bool $singleInheritance = false
	) : void {
		$def = $gen->def( $topName );
		$parent = $def['inheritance'] ?? null;
		foreach ( $gen->mixins( $topName ) as $m ) {
			$si = $singleInheritance || ( $m === $parent );
			self::collectAttributes( $gen, $m, $typeOpts, $attrs, $si );
		}
		'@phan-var array $def'; // @var array $def
		foreach ( $def['members'] ?? [] as $m ) {
			if ( $m['type'] === 'attribute' || $m['type'] === 'field' ) {
				$info = self::attributeInfo( $gen, $topName, $typeOpts, $m );
				$info['singleInheritance'] = $singleInheritance;
				$attrs[] = $info;
			}
		}
	}

	/**
	 * Extract attribute information from a member array.
	 * @param Generator $gen
	 * @param string $topName
	 * @param array $typeOpts
	 * @param array $m Member information
	 * @return array
	 */
	public static function attributeInfo( Generator $gen, string $topName, array $typeOpts, array $m ): array {
		Assert::invariant(
			$m['type'] === 'attribute' || $m['type'] === 'field',
			"Member is not an attribute"
		);
		$readonly = ( $m['readonly'] ?? false ) || ( $m['type'] === 'field' );
		$default = '';
		if ( ( $m['required'] ?? false ) === false && ( $m['default'] ?? null ) !== null ) {
			$val = $gen->valueToPHP( $m['default'] );
			$default = " ?? $val";
		}
		$fwd = $m;
		// Handle [PutForwards]
		$putForwards = Generator::extAttrNamed( $m, 'PutForwards' );
		if ( $putForwards ) {
			// We'll tweak types of setter based on referenced attribute
			$lhs = $m['idlType']['idlType'];
			$rhs = $putForwards['rhs']['value'];
			$fwd = $gen->memberDef( $lhs, $rhs );
			if ( $fwd === null || $fwd['type'] !== 'attribute' ) {
				throw new \Exception(
					"Can't find PutForwards: " . $lhs . '::' . $rhs
				);
			}
			$putForwards = $gen->map( $lhs, 'set', $rhs );
		}
		$info = [
			'topName' => $topName,
			'type' => $m['type'],
			'name' => $m['name'],
			'idlType' => $m['idlType'],
			'setterIdlType' => $fwd['idlType'],
			'isEnum' => strstr(
				$gen->typeToPHP(
					$m['idlType'], [ 'flagEnums' => true ] + $typeOpts
				),
				'/* enum */'
			) !== false,
			'readonly' => $readonly && !$putForwards,
			'getterType' => $gen->typeToPHP( $m['idlType'], [ 'returnType' => true ] + $typeOpts ),
			'getterTypeDoc' => $gen->typeToPHPDoc( $m['idlType'], $typeOpts ),
			'setterType' => $gen->typeToPHP( $fwd['idlType'], [ 'setter' => true ] + $typeOpts ),
			'setterTypeDoc' => $gen->typeToPHPDoc( $fwd['idlType'], [ 'setter' => true ] + $typeOpts ),
			'getter' => $gen->map( $topName, 'get', $m['name'] ),
			'setter' => ( $readonly && !$putForwards ) ? null :
				$gen->map( $topName, 'set', $m['name'] ),
			'default' => $default,
			'reflectType' => null,
			'reflectAttr' => strtolower( $m['name'] ),
			'reflectHelper' => false,
			'reflectLegacyNull' => false,
			'reflectEnum' => null,
			'putForwards' => $putForwards,
		];
		// Handle attributes with [Reflect]
		$reflect = Generator::extAttrNamed( $m, 'ReflectEnum' ) ??
				 Generator::extAttrNamed( $m, 'Reflect' ) ??
				 Generator::extAttrNamed( $m, 'ReflectURL' );
		if ( $reflect ) {
			$info['reflectType'] = $reflect['name'];
			if ( $reflect['name'] === 'ReflectEnum' ) {
				Assert::invariant(
					$reflect['rhs']['type'] === 'string-list',
					"Reflect argument of unexpected type"
				);
				$name = Generator::extAttrNamed( $m, 'Reflect' );
				if ( $name ) {
					$info['reflectAttr'] = json_decode( $name['rhs']['value'] );
				}
				$invalid = Generator::extAttrNamed( $m, 'ReflectInvalid' ) ??
						 Generator::extAttrNamed( $m, 'ReflectDefault' ) ??
						 [ 'rhs' => [ 'value' => 'null' ] ];
				$missing = Generator::extAttrNamed( $m, 'ReflectMissing' ) ??
						 Generator::extAttrNamed( $m, 'ReflectDefault' ) ??
						 [ 'rhs' => [ 'value' => 'null' ] ];
				$info['reflectEnum'] = [
					'values' => array_map(
						static function ( $value ) {
							return json_decode( $value['value'] );
						},
						$reflect['rhs']['value']
					),
					'invalid' => json_decode( $invalid['rhs']['value'] ),
					'missing' => json_decode( $missing['rhs']['value'] ),
				];
			} elseif ( $reflect['rhs'] !== null ) {
				Assert::invariant(
					$reflect['rhs']['type'] === 'string',
					"Reflect argument of unexpected type"
				);
				$info['reflectAttr'] = json_decode( $reflect['rhs']['value'] );
			}
			// Handle [LegacyNullToEmptyString]
			$info['reflectLegacyNull'] =
				Generator::extAttrsContain( $m['idlType'], 'LegacyNullToEmptyString' );
			// We're only handling some of these cases right now.
			$nullable = $m['idlType']['nullable'] ?? false;
			$info['reflectHelper'] = (
				$info['reflectType'] === 'Reflect' &&
				( ( $m['idlType']['idlType'] === 'boolean' && !$nullable ) ||
				 $m['idlType']['idlType'] === 'DOMString' )
			) || (
				$info['reflectType'] === 'ReflectEnum' &&
				$m['idlType']['idlType'] === 'DOMString'
			);
		}

		return $info;
	}

	/** @inheritDoc */
	protected function emitDictionary( string $topName, array $def ): void {
		$typeOpts = [ 'topName' => $topName ];
		$parentName = $this->parentName( $topName );
		$attrs = [];
		self::collectAttributes( $this->gen, $topName, $typeOpts, $attrs );

		$this->firstLine( $topName );
		// Getters and setters
		$this->emitGetterSetter( $topName, $attrs, $typeOpts );
		// ArrayAccess
		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' * @return bool' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetExists( $offset ): bool {' );
		$this->nl( 'switch ( $offset ) {' );
		foreach ( $attrs as $a ) {
			$this->nl( 'case ' . json_encode( $a['name'] ) . ':' );
		}
		$this->nl( "\treturn true;" );
		$this->nl( 'default:' );
		$this->nl( "\tbreak;" );
		$this->nl( '}' );
		$this->nl( "return false;" );
		$this->nl( '}' );
		$this->nl();

		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' * @return mixed' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetGet( $offset ) {' );
		$this->nl( 'return $this->$offset;' );
		$this->nl( '}' );
		$this->nl();
		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' * @param mixed $value' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetSet( $offset, $value ) : void {' );
		$this->nl( '$this->$offset = $value;' );
		$this->nl( '}' );
		$this->nl();
		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetUnset( $offset ) : void {' );
		$this->nl( 'unset( $this->$offset );' );
		$this->nl( '}' );
		$this->nl();

		// Cast from array
		$this->nl( '/**' );
		$this->nl( " * Create a $topName from an associative array." );
		$this->nl( ' *' );
		$this->nl( " * @param array|\\Wikimedia\\IDLeDOM\\$topName \$a" );
		$this->nl( " * @return \\Wikimedia\\IDLeDOM\\$topName" );
		$this->nl( ' */' );
		$cast = $this->map( $parentName, 'op', '_cast' );
		$this->nl( "public static function $cast( \$a ) {" );
		$this->nl( "if ( \$a instanceof \\Wikimedia\\IDLeDOM\\$topName ) {" );
		$this->nl( 'return $a;' );
		$this->nl( '}' );
		$this->nl( "return new class( \$a ) extends \\Wikimedia\\IDLeDOM\\$topName {" );
		$this->nl( "use $topName;" );
		$this->nl();
		$this->nl( '/** @var array */' );
		$this->nl( 'private $a;' );
		$this->nl();
		$this->nl( '/**' );
		$this->nl( ' * @param array $a' );
		$this->nl( ' */' );
		$this->nl( 'public function __construct( $a ) {' );
		$this->nl( '$this->a = $a;' );
		$this->nl( '}' );
		$this->nl();
		foreach ( $attrs as $a ) {
			$this->nl( '/**' );
			$this->nl( " * @return {$a['getterTypeDoc']}" );
			$this->nl( ' */' );
			$this->nl( "public function {$a['getter']}(){$a['getterType']} {" );
			$castStart = $castEnd = '';
			if ( $a['isEnum'] ) {
				$castStart = $a['idlType']['idlType'] . '::cast( ';
				$castEnd = ' )';
			}
			$this->nl(
				'return ' . $castStart .
				'$this->a[' . json_encode( $a['name'] ) . ']' .
				$a['default'] . $castEnd . ';'
			);
			$this->nl( '}' );
			$this->nl();
			$this->use( $a['idlType'], [ 'forceEnum' => true ] );
		}
		$this->nl( '};' );
		$this->nl( '}' );
		$this->nl();

		$this->nl( '}' );
	}

	/** @inheritDoc */
	protected function emitInterfaceMixin( string $topName, array $def ): void {
		$this->emitInterface( $topName, $def );
	}

	/** @inheritDoc */
	protected function emitInterface( string $topName, array $def ):void {
		$this->skip = true; // skip unless we actually emit something.
		$typeOpts = [ 'topName' => $topName ];

		$attrs = [];
		self::collectAttributes( $this->gen, $topName, $typeOpts, $attrs );

		$this->firstLine( $topName );
		if ( count( $attrs ) > 0 && $def['type'] !== 'interface mixin' ) {
			/* Only create getter/setter helpers for interfaces which
			 * contain attributes and are not mixins */
			$this->emitGetterSetter( $topName, $attrs, $typeOpts );
			$this->skip = false;
		}

		// Attributes with [PutForwards]
		foreach ( $attrs as $info ) {
			if ( $info['singleInheritance'] ) {
				continue; // skip attributes in single-inheritance chain
			}
			if ( !$info['putForwards'] ) {
				continue; // not a forwarded attribute
			}
			$this->skip = false;
			$this->use( $info['setterIdlType'], $typeOpts );
			$this->nl( '/**' );
			$this->nl( " * @param {$info['setterTypeDoc']} \$val" );
			$this->nl( ' */' );
			$this->nl( "public function {$info['setter']}( {$info['setterType']} \$val ) : void {" );
			$this->nl( "'@phan-var \\Wikimedia\\IDLeDOM\\$topName \$this';" );
			$this->nl( "// @var \\Wikimedia\\IDLeDOM\\$topName \$this" );
			$this->nl( "\$this->{$info['getter']}()->{$info['putForwards']}( \$val );" );
			$this->nl( "}" );
			$this->nl();
		}

		// Attributes with the [Reflect] property
		// XXX need to include attributes from mixins as well, but not from
		// our direct inheritance
		foreach ( $attrs as $info ) {
			if ( $info['singleInheritance'] ) {
				continue; // skip attributes in single-inheritance chain
			}
			if ( !$info['reflectHelper'] ) {
				continue; // not a reflected attribute
			}
			$this->skip = false;
			$this->use( $info['idlType'], $typeOpts );
			$attrName = var_export( $info['reflectAttr'], true );

			$this->nl( '/**' );
			$this->nl( " * @return {$info['getterTypeDoc']}" );
			$this->nl( ' */' );
			$this->nl( "public function {$info['getter']}(){$info['getterType']} {" );
			$this->nl( "'@phan-var \\Wikimedia\\IDLeDOM\\Element \$this';" );
			$this->nl( "// @var \\Wikimedia\\IDLeDOM\\Element \$this" );
			switch ( $info['idlType']['idlType'] ) {
			case 'DOMString':
				if ( $info['reflectType'] === 'ReflectEnum' ) {
					$novalue = ( $info['idlType']['nullable'] ?? false ) ?
							 "null" : "''";
					$this->nl( "\$val = \$this->getAttribute( $attrName );" );
					$this->nl( "if ( \$val !== null ) {" );
					$this->nl( "\$val = strtr( \$val, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz' );" );
					$this->nl( "switch ( \$val ) {" );
					foreach ( $info['reflectEnum']['values'] as $v ) {
						$v = var_export( $v, true );
						$this->nl( "case $v:" );
					}
					$this->nl( "\treturn \$val;" );
					$this->nl( "default:" );
					// Here we are in the "invalid value default" case
					// (or if none, then the "no state represented")
					if ( $info['reflectEnum']['invalid'] !== null ) {
						$v = var_export( $info['reflectEnum']['invalid'], true );
						$this->nl( "\treturn $v;" );
					} else {
						$this->nl( "\treturn $novalue;" );
					}
					$this->nl( "}" ); // close the switch statement
					// @phan-suppress-next-line PhanPluginDuplicateAdjacentStatement
					$this->nl( "}" ); // close the if statement
					// Here we are in the "missing value default" case
					// (or if none, then the "no state represented")
					if ( $info['reflectEnum']['missing'] !== null ) {
						$v = var_export( $info['reflectEnum']['missing'], true );
						$this->nl( "return $v;" );
					} else {
						$this->nl( "return $novalue;" );
					}
				} elseif ( $info['idlType']['nullable'] ?? false ) {
					$this->nl( "return \$this->getAttribute( $attrName );" );
				} else {
					$this->nl( "return \$this->getAttribute( $attrName ) ?? '';" );
				}
				break;
			case 'boolean':
				$this->nl( "return \$this->hasAttribute( $attrName );" );
				break;
			}
			$this->nl( '}' );
			$this->nl();
			if ( $info['setter'] === null ) {
				continue; // no setter
			}
			$trailer = $info['reflectLegacyNull'] ? " ?? ''" : '';
			$this->nl( '/**' );
			$this->nl( " * @param {$info['setterTypeDoc']} \$val" );
			$this->nl( ' */' );
			$this->nl( "public function {$info['setter']}( {$info['setterType']} \$val ) : void {" );
			$this->nl( "'@phan-var \\Wikimedia\\IDLeDOM\\Element \$this';" );
			$this->nl( "// @var \\Wikimedia\\IDLeDOM\\Element \$this" );
			switch ( $info['idlType']['idlType'] ) {
			case 'DOMString':
				if ( $info['idlType']['nullable'] ?? false ) {
					$this->nl( "if ( \$val !== null ) {" );
					$this->nl( "\$this->setAttribute( $attrName, \$val );" );
					$this->nl( "} else {" );
					$this->nl( "\$this->removeAttribute( $attrName );" );
					$this->nl( "}" );
				} else {
					$this->nl( "\$this->setAttribute( $attrName, \$val{$trailer} );" );
				}
				break;
			case 'boolean':
				$this->nl( "if ( \$val ) {" );
				$this->nl( "\$this->setAttribute( $attrName, '' );" );
				$this->nl( "} else {" );
				$this->nl( "\$this->removeAttribute( $attrName );" );
				$this->nl( "}" );
				break;
			}
			$this->nl( '}' );
			$this->nl();
		}

		// We may need to implement \ArrayAccess, countable, or the
		// stringifier as well.
		$specials = InterfaceBuilder::specialOperationHelper(
			$this->gen, $topName, $def
		);
		if ( count( $specials ) === 0 ) {
			$this->nl( '}' );
			return;
		}
		$this->skip = false;
		// declarations of all the specials
		foreach ( $specials as $s => $r ) {
			// Record types used
			$m = $r['ast'];
			// $this->use( $m['idlType'], $typeOpts );
			foreach ( $m['arguments'] ?? [] as $a ) {
			// $this->use( $a['idlType'], $typeOpts );
			}
			if ( $m['type'] !== 'operation' ) {
				continue;
			}
		}
		// Handle stringifier first
		$stringifier = $specials['stringifier'] ?? null;
		if ( $stringifier ) {
			$this->nl( '/**' );
			$this->nl( ' * @return string' );
			$this->nl( ' */' );
			$this->nl( 'public function __toString() : string {' );
			$this->emitThisHint( $topName );
			$this->nl( "return \$this->{$stringifier['funcName']}();" );
			$this->nl( '}' );
			$this->nl();
			unset( $specials['stringifier'] );
		}
		if ( count( $specials ) === 0 ) {
			$this->nl( '}' );
			return;
		}

		// Then countable
		$countable = $specials['countable'] ?? null;
		if ( $countable ) {
			$this->nl( '/**' );
			$this->nl( ' * @return int' );
			$this->nl( ' */' );
			$this->nl( 'public function count() : int {' );
			$this->emitThisHint( $topName );
			$this->nl( "return \$this->{$countable['funcName']}();" );
			$this->nl( '}' );
			$this->nl();
			unset( $specials['countable'] );
		}
		if ( count( $specials ) === 0 ) {
			$this->nl( '}' );
			return;
		}

		// Now ArrayAccess
		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' * @return bool' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetExists( $offset ): bool {' );
		$this->nl( 'return $this->offsetGet( $offset ) !== null;' );
		$this->nl( '}' );
		$this->nl();

		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' * @return mixed' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetGet( $offset ) {' );
		$this->emitThisHint( $topName );
		$this->nl( 'if ( is_numeric( $offset ) ) {' );
		$igetter = $specials['indexed getter'] ?? null;
		if ( $igetter ) {
			$this->nl( "return \$this->{$igetter['funcName']}( \$offset );" );
		} else {
			$this->nl( '/* Fall through */' );
		}
		$this->nl( '} elseif ( is_string( $offset ) ) {' );
		$ngetter = $specials['named getter'] ?? null;
		if ( $ngetter ) {
			$this->nl( "return \$this->{$ngetter['funcName']}( \$offset );" );
		} else {
			$this->nl( '/* Fall through */' );
		}
		$this->nl( '}' );
		$this->triggerError( [ 'offsetGet', 'offsetExists' ], 'offset' );
		$this->nl( 'return null;' );
		$this->nl( '}' );
		$this->nl();

		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' * @param mixed $value' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetSet( $offset, $value ) : void {' );
		$this->emitThisHint( $topName );
		$this->nl( 'if ( is_numeric( $offset ) ) {' );
		$setter = $specials['indexed setter'] ?? null;
		if ( $setter ) {
			$this->nl( "\$this->{$setter['funcName']}( \$offset, \$value );" );
		} else {
			$this->nl( '/* Fall through */' );
		}
		$this->nl( '} elseif ( is_string( $offset ) ) {' );
		$setter = $specials['named setter'] ?? null;
		if ( $setter ) {
			$this->nl( "\$this->{$setter['funcName']}( \$offset, \$value );" );
		} else {
			$this->nl( '/* Fall through */' );
		}
		$this->nl( '}' );
		$this->triggerError( 'offsetSet', 'offset' );
		$this->nl( '}' );
		$this->nl();

		$this->nl( '/**' );
		$this->nl( ' * @param mixed $offset' );
		$this->nl( ' */' );
		$this->nl( 'public function offsetUnset( $offset ) : void {' );
		$this->emitThisHint( $topName );
		$this->nl( 'if ( is_numeric( $offset ) ) {' );
		$deleter = $specials['indexed deleter'] ?? null;
		if ( $deleter ) {
			$this->nl( "\$this->{$deleter['funcName']}( \$offset );" );
		} else {
			$this->nl( '/* Fall through */' );
		}
		$this->nl( '} elseif ( is_string( $offset ) ) {' );
		$deleter = $specials['named deleter'] ?? null;
		if ( $deleter ) {
			$this->nl( "\$this->{$deleter['funcName']}( \$offset );" );
		} else {
			$this->nl( '/* Fall through */' );
		}
		$this->nl( '}' );
		$this->triggerError( 'offsetUnset', 'offset' );
		$this->nl( '}' );
		$this->nl();

		// If there's an indexed getter and a countable, then we can
		// provide a default implementation of the iterator
		// Note that pair iterators will not have indexed getters, so
		// we know this will be a value iterator.
		if ( $igetter && $countable ) {
			$iteratorName = $this->map( $igetter['topName'], 'op', '_iterable' );
			$iteratorType = $igetter['ast']['idlType'];
			// the getter is nullable, but the iterator is not!
			$iteratorType['nullable'] = false;
			// phan can't handle inferring the generic type here, so it's
			// just in a comment.  If this graduates to the 'real' return
			// type, then you'll need this 'use' statement:
			$this->use( $iteratorType, $typeOpts );
			$retTypeDoc = $this->gen->typeToPHPDoc( $iteratorType, $typeOpts );
			$this->nl( '/**' );
			$this->nl( " * @return \\Iterator<$retTypeDoc> Value iterator returning $retTypeDoc items" );
			$this->nl( ' */' );
			$this->nl( "public function $iteratorName() {" );
			$this->emitThisHint( $topName );
			$this->nl( "for ( \$i = 0; \$i < \$this->{$countable['funcName']}(); \$i++ ) {" );
			$this->nl( "yield \$this->{$igetter['funcName']}( \$i );" );
			$this->nl( "}\n}" );
			$this->nl();
		}
		$this->nl( '}' );
	}

	private function triggerError( $method, string $var, int $depth = 0 ) {
		if ( is_string( $method ) ) {
			$method = [ $method ];
		}
		$this->nl( '$trace = debug_backtrace();' );
		$this->nl( 'while (' );
		$this->nl( "\tcount( \$trace ) > 0 &&" );
		for ( $i = 0; $i < count( $method ); $i++ ) {
			$and = ( $i + 1 ) < count( $method ) ? ' &&' : '';
			$this->nl( "\t\$trace[0]['function'] !== " .
					  json_encode( $method[$i] ) . $and );
		}
		$this->nl( ') {' );
		$this->nl( 'array_shift( $trace );' );
		$this->nl( '}' );
		if ( count( $method ) > 1 ) {
			$this->nl( 'while (' );
			$this->nl( "\tcount( \$trace ) > 1 && (" );
			for ( $i = 0; $i < count( $method ); $i++ ) {
				$or = ( $i + 1 ) < count( $method ) ? ' ||' : '';
				$this->nl( "\t\$trace[1]['function'] === " .
						  json_encode( $method[$i] ) . $or );
			}
			$this->nl( ') ) {' );
			$this->nl( 'array_shift( $trace );' );
			$this->nl( '}' );
		}
		$this->nl( 'trigger_error(' );
		$this->nl( "\t'Undefined property' ." );
		$this->nl( "\t' via ' . ( \$trace[0]['function'] ?? '' ) . '(): ' . \${$var} ." );
		$this->nl( "\t' in ' . ( \$trace[0]['file'] ?? '' ) ." );
		$this->nl( "\t' on line ' . ( \$trace[0]['line'] ?? '' )," );
		$this->nl( "\tE_USER_NOTICE" );
		$this->nl( ');' );
	}

	private function emitGetterSetter( string $topName, array $attrs, array $typeOpts ): void {
		$needsSetter = false;
		foreach ( $attrs as $a ) {
			if ( !$a['readonly'] ) {
				$needsSetter = true;
				break;
			}
		}

		/* Create magic method __get dispatcher */
		$this->nl( '/**' );
		$this->nl( ' * @param string $name' );
		$this->nl( ' * @return mixed' );
		$this->nl( ' */' );
		$this->nl( 'public function __get( string $name ) {' );
		$this->emitThisHint( $topName );
		$this->nl( 'switch ( $name ) {' );
		foreach ( $attrs as $a ) {
			$name = $a['name'];
			$getter = $a['getter'];
			$this->nl( 'case ' . json_encode( $name ) . ':' );
			$this->nl( "\treturn \$this->$getter();" );
		}
		$this->nl( 'default:' );
		$this->nl( "\tbreak;" );
		$this->nl( '}' );
		$this->emitThisHint( 'Helper\\' . $topName );
		$this->nl( 'return $this->_getMissingProp( $name );' );
		$this->nl( '}' );
		$this->nl();

		/* Create magic method __isset dispatcher */
		$this->nl( '/**' );
		$this->nl( ' * @param string $name' );
		$this->nl( ' * @return bool' );
		$this->nl( ' */' );
		$this->nl( 'public function __isset( string $name ) : bool {' );
		$this->emitThisHint( $topName );
		$this->nl( 'switch ( $name ) {' );
		foreach ( $attrs as $a ) {
			$name = $a['name'];
			$getter = $a['getter'];
			$this->nl( 'case ' . json_encode( $name ) . ':' );
			if ( $a['idlType']['nullable'] ?? false ) {
				$this->nl( "\treturn \$this->$getter() !== null;" );
			} else {
				$this->nl( "\treturn true;" );
			}
		}
		$this->nl( 'default:' );
		$this->nl( "\tbreak;" );
		$this->nl( '}' );
		$this->nl( 'return false;' );
		$this->nl( '}' );
		$this->nl();

		/* Create magic method __set dispatcher */
		if ( $needsSetter ) {
			$this->nl( '/**' );
			$this->nl( ' * @param string $name' );
			$this->nl( ' * @param mixed $value' );
			$this->nl( ' */' );
			$this->nl( 'public function __set( string $name, $value ) : void {' );
			$this->emitThisHint( $topName );
			$this->nl( 'switch ( $name ) {' );
			foreach ( $attrs as $a ) {
				if ( $a['readonly'] ) {
					continue;
				}
				$name = $a['name'];
				$setter = $a['setter'];
				$this->nl( 'case ' . json_encode( $name ) . ':' );
				$this->nl( "\t\$this->$setter( \$value );" );
				$this->nl( "\treturn;" );
			}
			$this->nl( 'default:' );
			$this->nl( "\tbreak;" );
			$this->nl( '}' );
			$this->emitThisHint( 'Helper\\' . $topName );
			$this->nl( '$this->_setMissingProp( $name, $value );' );
			$this->nl( '}' );
			$this->nl();

			/* Create magic method __unset dispatcher */
			$this->nl( '/**' );
			$this->nl( ' * @param string $name' );
			$this->nl( ' */' );
			$this->nl( 'public function __unset( string $name ) : void {' );
			$this->emitThisHint( $topName );
			$this->nl( 'switch ( $name ) {' );
			foreach ( $attrs as $a ) {
				$name = $a['name'];
				$setter = $a['setter'];
				$this->nl( 'case ' . json_encode( $name ) . ':' );
				if (
					( $a['setterIdlType']['nullable'] ?? false ) &&
					!$a['readonly']
				) {
					$this->nl( "\t\$this->$setter( null );" );
					$this->nl( "\treturn;" );
				} else {
					$this->nl( "\tbreak;" );
				}
			}
			$this->nl( 'default:' );
			$this->nl( "\treturn;" );
			$this->nl( '}' );
			$this->triggerError( '__unset', 'name' );
			$this->nl( '}' );
			$this->nl();
		}
	}
}
