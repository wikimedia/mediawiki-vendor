<?php namespace SmashPig\Maintenance;

use SmashPig\Core\Context;

if ( !defined( 'RUN_MAINTENANCE_IF_MAIN' ) ) {
	print ( "This file must be included after MaintenanceBase.php\n" );
	exit( 1 );
}

// Wasn't included from the file scope, halt execution (probably wanted the class)
if ( !MaintenanceBase::shouldExecute() ) {
	return;
}

if ( !$maintClass || !class_exists( $maintClass ) ) {
	print ( "Cannot find maintenance class '$maintClass'; have you remembered to set it?\n" );
	exit( 1 );
}

// Get an object to start us off
$maintenance = new $maintClass();
if ( $maintenance instanceof MaintenanceBase ) {
	// Perform setup
	$maintenance->setup();

	// Now that we have a config node, check for disablement
	$config = Context::get()->getProviderConfiguration();
	if ( $config->nodeExists( 'disabled' ) && $config->val( 'disabled' ) ) {
		print ( 'Processor disabled, will not execute.' );
		exit( 1 );
	}

	$retval = $maintenance->execute();

	if ( $retval ) {
		exit( (int)$retval );
	}
} else {
	print ( "$maintClass is not a derivative of MaintenanceBase. Cannot execute.\n" );
	exit( 1 );
}
