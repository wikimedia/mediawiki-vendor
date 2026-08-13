<?php
declare( strict_types = 1 );

require __DIR__ . '/../vendor/autoload.php';

use Wikimedia\Codex\Tests\Integration\SnapshotTest;

SnapshotTest::writeSnapshots();
