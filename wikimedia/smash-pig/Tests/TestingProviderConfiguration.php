<?php

namespace SmashPig\Tests;

use SmashPig\Core\ProviderConfiguration;

/**
 * ProviderConfiguration that ignores /etc/smashpig and ~/.smashpig
 */
class TestingProviderConfiguration extends ProviderConfiguration {
	/**
	 * Set default search path to skip actual installed configuration like /etc
	 */
	protected function getDefaultSearchPath(): array {
		$searchPaths = [];
		if ( $this->provider !== self::NO_PROVIDER ) {
			$searchPaths[] = __DIR__ . "/../config/{$this->provider}/main.yaml";
		}
		$searchPaths[] = __DIR__ . '/../config/provider-defaults.yaml';
		return $searchPaths;
	}
}
