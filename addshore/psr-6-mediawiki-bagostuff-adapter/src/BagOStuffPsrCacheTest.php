<?php

namespace Addshore\Psr\Cache\MWBagOStuffAdapter;

use Cache\IntegrationTests\CachePoolTest;
use HashBagOStuff;

require_once __DIR__ . '/../vendor/cache/integration-tests/src/CachePoolTest.php';

/**
 * @covers \Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCache
 */
class BagOStuffPsrCacheTest extends CachePoolTest {

	private $bagOStuff;

	public function setUp() {
		// One HashBagOStuff per used per test (this is a cache after all)...
		$this->bagOStuff = new HashBagOStuff();

		parent::setUp();
	}

	public function createCachePool() {
		return new BagOStuffPsrCache( $this->bagOStuff );
	}

}
