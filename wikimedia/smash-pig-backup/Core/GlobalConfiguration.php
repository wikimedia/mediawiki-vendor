<?php
namespace SmashPig\Core;

/**
 * Cascading configuration using YAML files
 */
class GlobalConfiguration extends Configuration {

	/**
	 * Creates a GlobalConfiguration object
	 *
	 * @return GlobalConfiguration
	 */
	public static function create() {
		$config = new static();
		$config->loadDefaultConfig();

		return $config;
	}

	/**
	 * Creates a configuration object, overriding values from files.
	 *
	 * @param array|string $overridePath Extra configuration path(s) to search
	 *
	 * @return GlobalConfiguration or subclass
	 */
	public static function createWithOverrideFile( $overridePath ): GlobalConfiguration {
		$config = new static();

		$searchPath = array_merge(
			(array)$overridePath,
			$config->getDefaultSearchPath()
		);
		$config->loadConfigFromPaths( $searchPath );
		return $config;
	}

	protected function getDefaultSearchPath(): array {
		$searchPath = [];

		if ( isset( $_SERVER['HOME'] ) ) {
			// FIXME: But I don't understand why this key is missing during testing.
			$searchPath[] = "{$_SERVER['HOME']}/.smashpig/main.yaml";
		}
		$searchPath[] = '/etc/smashpig/main.yaml';
		$searchPath[] = __DIR__ . '/../config/main.yaml';
		return $searchPath;
	}

	protected function getDefaultOptions(): array {
		return [];
	}
}
