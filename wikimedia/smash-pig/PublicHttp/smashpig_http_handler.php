<?php namespace SmashPig;

use SmashPig\Core\Http\RequestHandler;

$file = __FILE__;

if ( !defined( "SMASHPIG_ENTRY_POINT" ) ) {
	define( "SMASHPIG_ENTRY_POINT", $file );

	$smashPigBaseDir = __DIR__ . '/../';
	require_once $smashPigBaseDir . '/vendor/autoload.php';

	RequestHandler::process()->send();
} else {
	$str = <<<EOT
SmashPig has detected that multiple execution entry points have been used in a
single session. Execution of the {$file} entry point cannot continue at this time.
EOT;
	print ( $str );
}
