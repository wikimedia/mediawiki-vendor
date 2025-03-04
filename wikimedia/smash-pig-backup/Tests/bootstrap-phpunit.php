<?php

require_once __DIR__ . '/../vendor/autoload.php';

// workaround for WMF CI as php-soap is not installed
if ( !extension_loaded( 'soap' ) ) {
	// phpcs:ignore
	class SoapClient {
	}
}
