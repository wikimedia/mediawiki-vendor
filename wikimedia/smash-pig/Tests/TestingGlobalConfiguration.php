<?php

namespace SmashPig\Tests;

use SmashPig\Core\GlobalConfiguration;

/**
 * Configuration that mocks all dbs and queues, and ignores override
 * files in /etc/smashpig/ and ~/.smashpig/
 */
class TestingGlobalConfiguration extends GlobalConfiguration {
	/**
	 * Set default search path to skip actual installed configuration like /etc
	 */
	protected function getDefaultSearchPath(): array {
		$searchPath = [];
		$searchPath[] = __DIR__ . '/data/test_global.yaml';
		$searchPath[] = __DIR__ . '/../config/main.yaml';
		return $searchPath;
	}

	public static function loadConfigWithLiteralOverrides( array $data ): GlobalConfiguration {
		$config = static::create();
		$config->override( $data );
		return $config;
	}
}
