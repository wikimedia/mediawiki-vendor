<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wikitext;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Fragments\PFragment;

/**
 * This class represents core wikitext concepts that are currently represented
 * as methods of Parser.php (in core) OR Parsoid.php (here) or other classes.
 * Care should be taken to have this class represent first-class wikitext
 * concepts and operations and not so much implementation concepts, but that is
 * understandably a hard line to draw. Given that, this suggestion is more of a
 * guideline to help with code hygiene.
 */
class Wikitext {
	/**
	 * Equivalent of 'preprocess' from Parser.php in core.
	 * - expands templates
	 * - replaces magic variables
	 *
	 * Notably, this doesn't support replacing template args from a frame,
	 * i.e. the preprocessing here is of *standalone wikitext*, not in
	 * reference to something else which is where a frame would be used.
	 *
	 * This does not run any Parser hooks either, but support for which
	 * could eventually be added that is triggered by input options.
	 *
	 * This also updates resource usage and returns an error if limits
	 * are breached.
	 *
	 * @param Env $env
	 * @param string $wt
	 * @return array{error:bool,src?:string,fragment?:PFragment}
	 *  - 'error' did we hit resource limits?
	 *  - 'src' expanded wikitext OR error message to print
	 *     FIXME: Maybe error message should be localizable
	 *  - 'fragment' Optional fragment (wikitext plus strip state)
	 */
	public static function preprocess( Env $env, string $wt ): array {
		$start = microtime( true );
		$ret = $env->getDataAccess()->preprocessWikitext( $env->getPageConfig(), $env->getMetadata(), $wt );
		$wikitextSize = strlen( $wt );
		if ( is_string( $ret ) ) {
			// FIXME: Should this bump be len($ret) - len($wt)?
			// I could argue both ways.
			$wikitextSize = strlen( $ret );
		}
		if ( !$env->bumpWt2HtmlResourceUse( 'wikitextSize', $wikitextSize ) ) {
			return [
				'error' => true,
				'src' => "wt2html: wikitextSize limit exceeded",
			];
		}

		if ( $env->profiling() ) {
			$profile = $env->getCurrentProfile();
			$profile->bumpMWTime( "Template", 1000 * ( microtime( true ) - $start ), "api" );
			$profile->bumpCount( "Template" );
		}

		return is_string( $ret ) ? [
			'error' => false,
			'src' => $ret,
		] : [
			'error' => false,
			'fragment' => $ret,
		];
	}
}
