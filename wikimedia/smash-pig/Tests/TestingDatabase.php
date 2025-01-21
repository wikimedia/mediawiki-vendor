<?php

namespace SmashPig\Tests;

class TestingDatabase {
	public static $classes = [
		'SmashPig\Core\DataStores\DamagedDatabase',
		'SmashPig\Core\DataStores\PaymentsFraudDatabase',
		'SmashPig\Core\DataStores\PaymentsInitialDatabase',
		'SmashPig\Core\DataStores\PendingDatabase',
	];

	public static function clearStatics() {
		foreach ( self::$classes as $className ) {
			$klass = new \ReflectionClass( $className );
			$dbProperty = $klass->getProperty( 'dbs' );
			$dbProperty->setAccessible( true );
			$dbProperty->setValue( [] );
		}
	}

	/**
	 * Initialize all the db tables
	 */
	public static function createTables() {
		foreach ( self::$classes as $className ) {
			$className::get()->createTables();
		}
	}
}
