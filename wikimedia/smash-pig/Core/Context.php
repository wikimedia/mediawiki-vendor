<?php
namespace SmashPig\Core;

use SmashPig\Core\Logging\Logger;

/**
 * Global context object -- useful for managing global variables when
 * we don't actually want globals or dedicated static classes.
 *
 * @package SmashPig\Core
 */
class Context {
	/** @var Context Reference to the current global context */
	protected static $instance;
	protected $contextId;
	protected $sourceRevision = 'unknown';
	protected $sourceName = 'SmashPig';
	protected $sourceType = 'listener';

	/** @var GlobalConfiguration|null Reference to the global configuration object */
	protected $globalConfiguration = null;

	/** @var ProviderConfiguration current provider-specific settings */
	protected $providerConfiguration = null;

	public static function init( GlobalConfiguration $config, $providerConfig = null ): void {
		if ( !self::$instance ) {
			if ( !$providerConfig ) {
				$providerConfig = ProviderConfiguration::createDefault( $config );
			}
			self::$instance = new Context();
			self::$instance->setGlobalConfiguration( $config );
			self::$instance->setProviderConfiguration( $providerConfig );
		}
	}

	/**
	 * Obtains the current context object
	 * @return static|null
	 */
	public static function get(): ?Context {
		return self::$instance;
	}

	/**
	 * Sets the current context, returning the displaced context
	 * @param Context|null $c
	 * @return Context|null
	 */
	public static function set( ?Context $c = null ): ?Context {
		$old = self::$instance;
		self::$instance = $c;

		return $old;
	}

	public function __construct( $cid = null ) {
		if ( !$cid ) {
			$this->contextId = sprintf( 'SPCID-%010d', mt_rand( 10000, pow( 2, 31 ) - 1 ) );
		} else {
			$this->contextId = $cid;
		}

		$versionStampPath = __DIR__ . "/../.version-stamp";
		$this->setVersionFromFile( $versionStampPath );
	}

	/**
	 * Sets the version string to the contents of a file, if it exists
	 * @param string $versionStampPath
	 * @return Context
	 */
	public function setVersionFromFile( string $versionStampPath ): Context {
		if ( file_exists( $versionStampPath ) ) {
			$versionId = file_get_contents( $versionStampPath );
			if ( $versionId !== false ) {
				$this->sourceRevision = trim( $versionId );
			}
		}
		return $this;
	}

	/**
	 * Gets the global context identifier - this is used for logging, filenames,
	 * or other identifiers specific to the current job.
	 *
	 * @return string Format of SPCID-[1-9][0-9]{8}
	 */
	public function getContextId(): string {
		return $this->contextId;
	}

	/**
	 * Sets a configuration object associated with this context.
	 *
	 * All calls to get configuration options need to happen through the
	 * context object; which means that if we ever support changing configurations
	 * based on account; this will somehow require us to support either
	 * stacked configurations or stacked contexts...
	 *
	 * @param GlobalConfiguration $config
	 * @return Context
	 */
	protected function setGlobalConfiguration( GlobalConfiguration $config ): Context {
		$this->globalConfiguration = $config;
		return $this;
	}

	/**
	 * Gets the global configuration object associated with the current context.
	 *
	 * Set the global configuration using init()
	 *
	 * @return GlobalConfiguration
	 */
	public function getGlobalConfiguration(): GlobalConfiguration {
		return $this->globalConfiguration;
	}

	/**
	 * @return ProviderConfiguration
	 */
	public function getProviderConfiguration(): ProviderConfiguration {
		return $this->providerConfiguration;
	}

	public function setProviderConfiguration( ProviderConfiguration $configuration ): Context {
		$this->providerConfiguration = $configuration;
		// FIXME: Terminate logger crap with extreme prejudice
		Logger::init(
			$configuration->val( 'logging/root-context' ),
			$configuration->val( 'logging/log-level' ),
			$configuration,
			$configuration->getProviderName()
		);
		return $this;
	}

	/**
	 * Get the revision ID to tag queue messages
	 * @see setVersionFromFile
	 *
	 * @return string
	 */
	public function getSourceRevision(): string {
		return $this->sourceRevision;
	}

	/**
	 * Get an identifier for the application to tag queue messages
	 *
	 * @return string
	 */
	public function getSourceName(): string {
		return $this->sourceName;
	}

	/**
	 * Set an identifier for the application to tag queue messages
	 *
	 * @param string $sourceName
	 * @return Context
	 */
	public function setSourceName( string $sourceName ): Context {
		$this->sourceName = $sourceName;
		return $this;
	}

	/**
	 * Get the application type used in queue messages
	 *
	 * @return string
	 */
	public function getSourceType(): string {
		return $this->sourceType;
	}

	/**
	 * Set the application type used in queue messages
	 *
	 * @param string $sourceType
	 * @return Context
	 */
	public function setSourceType( string $sourceType ): Context {
		$this->sourceType = $sourceType;
		return $this;
	}
}
