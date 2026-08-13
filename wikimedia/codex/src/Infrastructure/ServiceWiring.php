<?php
declare( strict_types = 1 );

/**
 * ServiceWiring.php
 *
 * This file returns the array loaded by the CodexServices class
 * for use through `Wikimedia\CodexServices::getInstance()`. Each service is associated with a key (service name),
 * and its value is a closure that returns an instance of the service. This setup allows for
 * dependency injection, ensuring services are instantiated only when needed.
 *
 * Reminder:
 *  - ServiceWiring is NOT a cache for arbitrary singletons.
 *
 * Services include various UI component builders, renderers, and utilities such as the
 * Mustache template engine and Localization for localization. These services are integral to
 * the Codex design system for generating reusable, standardized components.
 *
 * @category Infrastructure
 * @package  Codex\Infrastructure
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

use GuzzleHttp\Psr7\ServerRequest;
use Wikimedia\Codex\ParamValidator\ParamValidator;
use Wikimedia\Codex\ParamValidator\ParamValidatorCallbacks;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;
use Wikimedia\Services\ServiceContainer;

/** @phpcs-require-sorted-array */
return [
	'ParamValidator' => static function ( ServiceContainer $services ): ParamValidator{
		return new ParamValidator( $services->getService( 'ParamValidatorCallbacks' ) );
	},

	'ParamValidatorCallbacks' => static function (): ParamValidatorCallbacks {
		$request = ServerRequest::fromGlobals();
		return new ParamValidatorCallbacks( $request->getQueryParams() );
	},

	'Sanitizer' => static function () {
		return new Sanitizer();
	},

	'TemplateParser' => static function ( ServiceContainer $services ) {
		$templatePath = __DIR__ . '/../../resources/templates';

		return new TemplateParser( $templatePath );
	},
];
