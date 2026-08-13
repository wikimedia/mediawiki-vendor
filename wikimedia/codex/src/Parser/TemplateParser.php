<?php
declare( strict_types = 1 );

/**
 * TemplateParser.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `TemplateParser` class, which is responsible
 * for compiling and rendering Mustache templates with localization and helper support.
 *
 * The TemplateParser centralizes template compilation and rendering logic,
 * enhancing reusability and maintainability.
 *
 * @category Parser
 * @package  Codex\Parser
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Parser;

use LightnCandy\Flags;
use LightnCandy\LightnCandy;
use RuntimeException;

/**
 * TemplateParser is responsible for compiling and rendering Mustache templates.
 *
 * This class provides methods to compile Mustache templates into PHP rendering functions.
 *
 * @category Parser
 * @package  Codex\Parser
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class TemplateParser {

	/**
	 * Path to the Mustache templates directory.
	 */
	private string $templateDir;

	/**
	 * @var array<string,callable> Array of cached rendering functions.
	 */
	private array $renderers = [];

	/**
	 * Compilation flags for LightnCandy.
	 */
	private int $compileFlags;

	/**
	 * Constructor to initialize the TemplateParser.
	 *
	 * @since 0.3.0
	 *
	 * @param string $templateDir Path to the template directory.
	 */
	public function __construct( string $templateDir ) {
		$this->templateDir = $templateDir;

		$this->compileFlags =
			Flags::FLAG_ERROR_EXCEPTION |
			Flags::FLAG_HANDLEBARS |
			Flags::FLAG_ADVARNAME |
			Flags::FLAG_RUNTIMEPARTIAL |
			Flags::FLAG_RENDER_DEBUG |
			Flags::FLAG_MUSTACHE |
			Flags::FLAG_ERROR_EXCEPTION |
			Flags::FLAG_NOHBHELPERS |
			Flags::FLAG_MUSTACHELOOKUP;
	}

	/**
	 * Compiles the Mustache template into a PHP rendering function.
	 *
	 * @since 0.3.0
	 *
	 * @param string $templateName Name of the template file (without extension).
	 *
	 * @return callable Render function for the template.
	 * @throws RuntimeException If the template file cannot be found or compilation fails.
	 * @suppress PhanTypeMismatchArgument
	 */
	public function compile( string $templateName ): callable {
		if ( isset( $this->renderers[$templateName] ) ) {
			return $this->renderers[$templateName];
		}

		$templatePath = "$this->templateDir/$templateName.mustache";

		if ( !file_exists( $templatePath ) ) {
			throw new RuntimeException( "Template file not found: $templatePath" );
		}

		$templateContent = file_get_contents( $templatePath );

		if ( $templateContent === false ) {
			throw new RuntimeException( "Unable to read template file: $templatePath" );
		}

		$phpCode = LightnCandy::compile( $templateContent, [
			'flags' => $this->compileFlags,
			'basedir' => $this->templateDir,
			'fileext' => '.mustache',
			'partialresolver' => function ( $cx, $partialName ) use ( $templateName ) {
				$filename = "$this->templateDir/$partialName.mustache";
				if ( !file_exists( $filename ) ) {
					throw new RuntimeException(
						sprintf(
							'Could not compile template `%s`: Could not find partial `%s` at %s',
							$templateName,
							$partialName,
							$filename
						)
					);
				}

				$fileContents = file_get_contents( $filename );

				if ( $fileContents === false ) {
					throw new RuntimeException(
						sprintf(
							'Could not compile template `%s`: Could not find partial `%s` at %s',
							$templateName,
							$partialName,
							$filename
						)
					);
				}

				return $fileContents;
			},
		] );

		if ( !$phpCode ) {
			throw new RuntimeException( "Failed to compile template: $templateName" );
		}

		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.eval
		$renderFunction = eval( $phpCode );
		if ( !is_callable( $renderFunction ) ) {
			throw new RuntimeException( "Compiled template is not callable: $templateName" );
		}

		$this->renderers[$templateName] = $renderFunction;

		return $renderFunction;
	}

	/**
	 * Processes the template with provided data and returns the rendered HTML.
	 *
	 * @since 0.3.0
	 *
	 * @param string $templateName Name of the template file (without extension).
	 * @param array $data Data to render within the template.
	 *
	 * @return string Rendered HTML.
	 * @throws RuntimeException If rendering fails.
	 */
	public function processTemplate( string $templateName, array $data ): string {
		$renderFunction = $this->compile( $templateName );

		return $renderFunction( $data );
	}
}
