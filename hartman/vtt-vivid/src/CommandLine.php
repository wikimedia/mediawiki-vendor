<?php

namespace WebVTT;

/**
 * Common logic for command-line scripts.
 */
class CommandLine {

	/**
	 * Locate and require the Composer autoloader.
	 */
	public static function setupAutoloader(): void {
		$files = [
			__DIR__ . '/../vendor/autoload.php',
			__DIR__ . '/../../../autoload.php'
		];

		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		fwrite( STDERR, "Error: Could not find Composer autoloader.\n" );
		exit( 1 );
	}

	/**
	 * Check if the input file exists, or exit with an error.
	 *
	 * @param string $path
	 */
	public static function checkInputFile( string $path ): void {
		if ( !file_exists( $path ) ) {
			fwrite( STDERR, "Error: Input file not found: $path\n" );
			exit( 1 );
		}
	}

	/**
	 * Print usage and exit.
	 *
	 * @param string $message
	 * @param int $exitCode
	 */
	public static function usage( string $message, int $exitCode = 1 ): void {
		echo $message . "\n";
		exit( $exitCode );
	}
}
