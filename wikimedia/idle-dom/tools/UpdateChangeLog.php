<?php

namespace Wikimedia\IDLeDOM\Tools;

class UpdateChangeLog {
	public const PACKAGE_NAME = 'IDLeDOM';

	public static function main() {
		$changeLogPath = __DIR__ . '/../CHANGELOG.md';
		$changeLog = file_get_contents( $changeLogPath );
		$changeLog = preg_replace_callback(
			'/^# ' . preg_quote( self::PACKAGE_NAME, '/' ) .
			' (x\.x\.x|\d+\.\d+\.\d+)(.*)$/m',
			static function ( $matches ) {
				$line = '# ' . self::PACKAGE_NAME;
				if ( $matches[1] === 'x.x.x' ) {
					// Do a release!
					$version = '0.7.1'; // XXX FIXME to fetch & bump version
					$date = date( 'Y-m-d' );
					return "$line $version ($date)";
				} else {
					// Bump after a release
					return "$line x.x.x (not yet released)\n\n" . $matches[0];
				}
			},
			$changeLog, 1, $count );
		if ( $count != 1 ) {
			throw new \Exception( "Changelog entry not found!" );
		}
		file_put_contents( $changeLogPath, $changeLog );
	}
}
