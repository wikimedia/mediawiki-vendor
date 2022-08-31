<?php

namespace SmashPig\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\GlobalConfiguration;
use SmashPig\Core\ProviderConfiguration;

class TestingContext extends Context {
	public $providerConfigurationOverride;

	public static function init(
		GlobalConfiguration $config, $providerConfig = null
	): void {
		// Override the existing context
		Context::$instance = new TestingContext();
		if ( !$providerConfig ) {
			$providerConfig = TestingProviderConfiguration::createDefault( $config );
		}
		Context::$instance->setProviderConfiguration( $providerConfig );
		Context::$instance->setGlobalConfiguration( $config );
		TestingDatabase::createTables();
		self::initializeQueues( $config );
	}

	protected static function initializeQueues( GlobalConfiguration $config ): void {
		foreach ( $config->val( 'data-store' ) as $name => $definition ) {
			if (
				array_key_exists( 'class', $definition ) &&
				// FIXME should really only do this for \PHPQueue\Backend\PDO
				is_subclass_of( $definition['class'], '\PHPQueue\Interfaces\FifoQueueStore' )
			) {
				// FIXME PDO backend should know which table to create without an arg
				if ( array_key_exists( 'queue', $definition['constructor-parameters'][0] ) ) {
					$table = $definition['constructor-parameters'][0]['queue'];
				} else {
					$table = $name;
				}
				QueueWrapper::getQueue( $name )->createTable( $table );
			}
		}
	}

	public function getProviderConfiguration(): ProviderConfiguration {
		if ( $this->providerConfigurationOverride ) {
			return $this->providerConfigurationOverride;
		}
		return parent::getProviderConfiguration();
	}

}
