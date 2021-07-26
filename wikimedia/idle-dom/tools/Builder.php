<?php

namespace Wikimedia\IDLeDOM\Tools;

class Builder {
	/** @var Generator */
	protected $gen;
	/** @var Emitter */
	protected $e;
	/** @var array */
	protected $options;
	/** @var bool */
	protected $skip = false;
	/** @var array<string,bool> */
	protected $used = [];

	/**
	 * Protected constructor, not for use outside the class.
	 * @param Generator $gen The generator, for type and name mapping.
	 * @param array $options Builder options
	 */
	protected function __construct( Generator $gen, array $options ) {
		$this->gen = $gen;
		$this->e = new Emitter();
		$this->options = $options;
		$this->skip = false;
	}

	/**
	 * Helper method: proxies to the Emitter.
	 * @param string $line
	 */
	protected function nl( string $line = '' ): void {
		$this->e->nl( $line );
	}

	/**
	 * Helper method: stringifies the Emitter.
	 * @return string
	 */
	public function __toString(): string {
		return (string)$this->e;
	}

	/**
	 * Helper method: proxies to the generator's name map.
	 * @param string $topName
	 * @param string $type
	 * @param string $name
	 * @return string
	 */
	protected function map( string $topName, string $type, string $name ): string {
		return $this->gen->map( $topName, $type, $name );
	}

	/**
	 * Helper method: record that a given type has been used.
	 * @param array $ty WebIDL AST type
	 * @param array $typeOpts Optional type options (top-level name)
	 */
	protected function use( array $ty, array $typeOpts = [] ):void {
		if ( $ty['union'] ?? false ) {
			foreach ( $ty['idlType'] as $subtype ) {
				$this->use( $subtype, $typeOpts );
			}
			return;
		}
		$generic = $ty['generic'] ?? '';
		if ( $generic !== '' ) {
			foreach ( $ty['idlType'] as $subtype ) {
				$this->use( $subtype, $typeOpts );
			}
			return;
		}
		$name = $ty['idlType'];
		$typedef = $this->gen->typedef( $name );
		if ( $typedef !== null ) {
			$this->use( $typedef, $typeOpts );
			return;
		}
		$def = $this->gen->def( $name );
		if ( $def !== null ) {
			if (
				$name !== ( $typeOpts['topName'] ?? null ) &&
				(
					( $typeOpts['forceEnum'] ?? false ) ||
					$def['type'] !== 'enum'
				)
			) {
				$this->used[$name] = true;
			}
		}
	}

	/**
	 * Replace a marker with use statements, based on the contents of the
	 * used array.
	 * @param string $namespace The top-level namespace
	 */
	protected function addUseStatements( string $namespace ): void {
		ksort( $this->used, SORT_STRING | SORT_FLAG_CASE );
		$useStmts = [];
		foreach ( $this->used as $name => $ignore ) {
			$useStmts[] = "use $namespace\\$name;";
		}
		$this->e->replaceMarker( 'UseStatements', ...$useStmts );
	}

	/**
	 * Tell 'phan' that traits are supposed to be used inside an interface
	 * of the proper type.  This avoids the need to create abstract
	 * methods in the trait, which have an unfortunate side-effect of
	 * overriding inherited methods from the parent class.
	 *
	 * @param string $topName
	 */
	protected function emitThisHint( string $topName ) {
		$this->nl( "'@phan-var \\Wikimedia\\IDLeDOM\\$topName \$this';" );
		$this->nl( "// @var \\Wikimedia\IDLeDOM\\$topName \$this" );
	}

	/**
	 * @param string $topName The name of the definition
	 * @param string $name The name of the member
	 * @param array $m The WebIDL AST for the member
	 */
	protected function emitMemberConstructor( string $topName, string $name, array $m ) {
	}

	/**
	 * @param string $topName The name of the definition
	 * @param string $name The name of the member
	 * @param array $m The WebIDL AST for the member
	 */
	protected function emitMemberAttribute( string $topName, string $name, array $m ) {
	}

	/**
	 * @param string $topName The name of the definition
	 * @param string $name The name of the member
	 * @param array $m The WebIDL AST for the member
	 */
	protected function emitMemberOperation( string $topName, string $name, array $m ) {
	}

	/**
	 * @param string $topName The name of the definition
	 * @param string $name The name of the member
	 * @param array $m The WebIDL AST for the member
	 */
	protected function emitMemberConst( string $topName, string $name, array $m ) {
	}

	/**
	 * @param string $topName The name of the definition
	 * @param string $name The name of the member
	 * @param array $m The WebIDL AST for the member
	 */
	protected function emitMemberIterable( string $topName, string $name, array $m ) {
	}

	/**
	 * Dispatch to a specialized member visitor.
	 * @param string $topName The name of the definition
	 * @param array $m The WebIDL AST for the member
	 */
	protected function emitMember( string $topName, array $m ) {
		$methodName = 'emitMember' .
			str_replace( ' ', '', ucwords( $m['type'] ) );
		$name = $m['type'] === 'constructor' ? '__construct' :
			  ( $m['type'] === 'iterable' ? 'getIterator' :
				( $m['name'] ?? '' ) );
		$this->$methodName( $topName, $name, $m );
		$this->nl();
	}

	/**
	 * @param string $topName The name of the definition
	 * @param array $def The WebIDL AST
	 */
	protected function emitInterface( string $topName, array $def ): void {
		$this->skip = true;
	}

	/**
	 * @param string $topName The name of the definition
	 * @param array $def The WebIDL AST
	 */
	protected function emitDictionary( string $topName, array $def ): void {
		$this->skip = true;
	}

	/**
	 * @param string $topName The name of the definition
	 * @param array $def The WebIDL AST
	 */
	protected function emitCallbackInterface( string $topName, array $def ): void {
		$this->skip = true;
	}

	/**
	 * @param string $topName The name of the definition
	 * @param array $def The WebIDL AST
	 */
	protected function emitInterfaceMixin( string $topName, array $def ): void {
		$this->skip = true;
	}

	/**
	 * @param string $topName The name of the definition
	 * @param array $def The WebIDL AST
	 */
	protected function emitCallback( string $topName, array $def ): void {
		$this->skip = true;
	}

	/**
	 * @param string $topName The name of the definition
	 * @param array $def The WebIDL AST
	 */
	protected function emitEnum( string $topName, array $def ): void {
		$this->skip = true;
	}

	/**
	 * Dispatch to specialized emitter.
	 * @param array $def
	 */
	protected function emitDefinition( array $def ):void {
		$methodName = 'emit' . str_replace( ' ', '', ucwords( $def['type'] ) );
		$this->$methodName( $def['name'], $def );
	}

	/**
	 * Main entry point: build a single definition.
	 * @param Generator $gen
	 * @param array $def WebIDL AST definition
	 * @param array $options Builder options
	 * @return ?string The output, or null to skip this definition.
	 */
	public static function emit( Generator $gen, array $def, array $options ): ?string {
		$b = new static( $gen, $options );
		$b->emitDefinition( $def );
		$b->addUseStatements( $options['namespace'] ?? 'Wikimedia\IDLeDOM' );
		return $b->skip ? null : (string)$b;
	}
}
