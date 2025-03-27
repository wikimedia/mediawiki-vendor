<?php
namespace SmashPig\Core;

use Symfony\Component\Yaml\Parser;

/**
 * Cascading configuration using YAML files
 */
abstract class Configuration {

	/** @var array K/V array of configuration options for the initialized node */
	protected $options = [];

	/** @var array keyed on class name that stores persistent objects */
	protected $objects = [];

	public function loadDefaultConfig() {
		$this->loadConfigFromPaths( $this->getDefaultSearchPath() );
	}

	abstract protected function getDefaultSearchPath();

	/**
	 * Load a search path consisting of single files or globs
	 *
	 * Settings from files earlier in the list take precedence.  The funky
	 * "view" override happens here in a second step, with view data from all
	 * source files taking precedence over default data from all files.
	 *
	 * @param array $searchPath
	 */
	public function loadConfigFromPaths( array $searchPath ) {
		$paths = $this->expandSearchPathToActual( $searchPath );

		// Reset to empty set.
		$this->options = [];

		// Attempt to load the configuration files from disk
		$configs = [];
		$yamlParser = new Parser();
		foreach ( $paths as $path ) {
			$config = $yamlParser->parse( file_get_contents( $path ) );
			if ( !is_array( $config ) ) {
				throw new \RuntimeException( "Bad config file format: '$path'" );
			}
			$configs[] = $config;
		}

		// Pull in all `default` sections first.
		// FIXME: The reverse thing is silly, but it's much simpler to merge
		// the sources up front than keep them distinct and search through them
		// at runtime for the first matching key.
		foreach ( array_reverse( $configs ) as $config ) {
			if ( !empty( $config ) ) {
				$this->override( $config );
			}
		}
	}

	/**
	 * Flatten and unglob the search path.
	 *
	 * @param array $searchPath File paths or globs
	 * @return array Actual files discovered in the path.
	 */
	protected function expandSearchPathToActual( array $searchPath ) {
		$paths = array_reduce( $searchPath, static function ( $carry, $pattern ) {
			$matchingPaths = glob( $pattern );
			if ( $matchingPaths === false ) {
				throw new \RuntimeException( "Illegal glob while matching {$pattern}" );
			}
			return array_merge( $carry, $matchingPaths );
		}, [] );

		return $paths;
	}

	/**
	 * Override configuration with an array of data
	 *
	 * Note that these overrides take precedence over every configuration file,
	 * so any usage outside of this class or tests will be subverting the
	 * expected cascading priority.
	 *
	 * @param array $data
	 * @throws SmashPigException
	 */
	public function override( array $data ) {
		static::treeMerge( $this->options, $data );
	}

	/**
	 * For testing: provide a specific instance of an object to fulfil requests
	 * for a specific node. Helpful when using test library mocks that you can't
	 * declaratively configure with constructor parameters.
	 *
	 * @param string $node
	 * @param object $object
	 */
	public function overrideObjectInstance( string $node, $object ) {
		$this->objects[$node] = $object;
	}

	/**
	 * Obtain a value from the configuration. If the key does not exist this will throw an
	 * exception.
	 *
	 * @param string $path Parameter node to obtain. If this contains '/' it is assumed that the
	 *                            value is contained under additional keys.
	 * @return mixed
	 * @throws ConfigurationKeyException
	 */
	public function val( string $path ) {
		/*
		 * Magic "/" returns the entire configuration tree.
		 *
		 * Question: Is this "/" trick intuitive enough to absolve it of being
		 * a magic number?
		 *
		 * Note: Never log this tree insecurely, it will contain processor
		 * credentials and other sensitive information.
		 */
		if ( $path === '/' ) {
			return $this->options;
		}

		$segments = explode( '/', $path );

		$currentNode = $this->options;
		foreach ( $segments as $segment ) {
			if ( array_key_exists( $segment, $currentNode ) ) {
				$currentNode = $currentNode[$segment];
			} else {
				throw new ConfigurationKeyException( "Configuration key '{$path}' does not exist.", $path );
			}
		}

		return $currentNode;
	}

	/**
	 * Creates an object from the configuration file. This works by looking up the configuration
	 * key name which will be an array with at least a subkey of 'class'. The class will then be
	 * instantiated with any arguments as given in the subkey 'constructor-parameters'.
	 *
	 * When arguments are given it should be a simple list with arguments in the expected order.
	 *
	 * Example:
	 * data_source:
	 *      class: DataSourceClass
	 *      constructor-parameters:
	 *          - argument1
	 *          - foo/bar/baz
	 *
	 * @param string $node Parameter node to obtain. If this contains '/'
	 *                           it is assumed that the value is contained
	 *                           under additional keys.
	 * @param bool $persistent If true the object is saved for future calls.
	 * @return mixed|object
	 * @throws ConfigurationKeyException
	 * @throws \ReflectionException
	 */
	public function object( string $node, bool $persistent = true ) {
		// First look and see if we already have a $persistent object.
		if ( array_key_exists( $node, $this->objects ) ) {
			return $this->objects[$node];
		}

		$className = $this->val( $node . '/class' );

		// Optional keys
		$arguments = [];
		// It would be nice to be able to provide other objects defined
		// in config as arguments. We might have had that pre-simplification
		// with nodes that referred to other nodes.
		if ( $this->nodeExists( $node . '/constructor-parameters' ) ) {
			$arguments = $this->val( $node . '/constructor-parameters' );
		}

		$reflectedObj = new \ReflectionClass( $className );
		$obj = $reflectedObj->newInstanceArgs( $arguments );

		if ( $persistent ) {
			$this->objects[$node] = $obj;
		}
		return $obj;
	}

	/**
	 * Determine if a given configuration node exists in the loaded configuration.
	 *
	 * @param string $node Node path; ie: logging/logstreams/syslog/class
	 *
	 * @return bool True if the node exists
	 */
	public function nodeExists( string $node ): bool {
		try {
			$this->val( $node );
			return true;
		} catch ( ConfigurationKeyException $ex ) {
			return false;
		}
	}

	/**
	 * Merge two arrays recursively. The $graft array will overwrite any value in the $base
	 * array where the $base array does not have an array at that node. If it does have an
	 * array the merge will continue recursively.
	 *
	 * @param array &$base The base array to merge into
	 * @param array $graft Values to merge into the $base
	 *
	 * @param string $myRoot Internal recursion state: parent node path so far,
	 * or empty string to begin.
	 * @throws SmashPigException
	 */
	protected static function treeMerge( array &$base, array $graft, string $myRoot = '' ) {
		foreach ( $graft as $graftNodeName => $graftNodeValue ) {
			$node = ( $myRoot ? "{$myRoot}/{$graftNodeName}" : $graftNodeName );

			if ( array_key_exists( $graftNodeName, $base ) ) {
				$baseNodeRef = &$base[$graftNodeName];
				// Nodes that are present in the base and in the graft

				if (
					is_array( $graftNodeValue ) &&
					self::isMergable( $baseNodeRef, $graftNodeValue )
				) {
					// Recursively merge arrays.
					static::treeMerge( $baseNodeRef, $graftNodeValue, $node );
				} else {
					$baseNodeRef = $graftNodeValue;
				}
			} else {
				// Nodes that are only present in the graft
				$base[$graftNodeName] = $graftNodeValue;
			}
		}
	}

	/**
	 * Check that valueB can be merged on top of valueA.
	 */
	protected static function isMergable( $valueA, $valueB ) {
		if ( gettype( $valueA ) !== gettype( $valueB ) ) {
			// Plain old different type
			return false;
		}

		// Test for numeric vs map "array"--gotta love it.
		if ( is_array( $valueA ) && is_array( $valueB )

			// If either is empty, don't sweat it.
			&& $valueA && $valueB

			// If either has element [0], so must the other.
			&& ( array_key_exists( 0, $valueA )
				xor array_key_exists( 0, $valueB ) )
		) {
			return false;
		}

		// Feeling lucky.
		return true;
	}
}
