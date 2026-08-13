<?php
declare( strict_types = 1 );

/**
 * ButtonRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `ButtonRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
 * component object is rendered according to Codex design system standards.
 *
 * @category Renderer
 * @package  Codex\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Renderer;

use InvalidArgumentException;
use Wikimedia\Codex\Component\Button;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * ButtonRenderer is responsible for rendering the HTML markup
 * for a Button component using a Mustache template.
 *
 * This class uses the `TemplateParser` and `Sanitizer` utilities to manage
 * the template rendering process, ensuring that the component object's HTML
 * output adheres to the Codex design system's standards.
 *
 * @category Renderer
 * @package  Codex\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class ButtonRenderer extends Renderer {

	/**
	 * The template parser instance.
	 */
	private TemplateParser $templateParser;

	/**
	 * Constructor to initialize the ButtonRenderer with a sanitizer and a template parser.
	 *
	 * @since 0.1.0
	 * @param Sanitizer $sanitizer The sanitizer instance used for content sanitization.
	 * @param TemplateParser $templateParser The template parser instance used for rendering templates.
	 */
	public function __construct( Sanitizer $sanitizer, TemplateParser $templateParser ) {
		parent::__construct( $sanitizer );
		$this->templateParser = $templateParser;
	}

	/**
	 * Renders the HTML for a button component.
	 *
	 * Uses the provided Button component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The Button object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof Button ) {
			throw new InvalidArgumentException(
				"Expected instance of Button, got " . get_class( $component )
			);
		}
		$isLink = $component->getHref() !== null;
		$buttonData = [
			'id' => $component->getId(),
			'label-html' => $this->sanitizer->sanitizeText( $component->getLabel() ),
			'action' => $component->getAction(),
			'defaultAction' => $component->getAction() === 'default',
			'weight' => $component->getWeight(),
			'type' => $component->getType(),
			'defaultWeight' => $component->getWeight() === 'normal',
			'size' => $component->getSize(),
			'defaultSize' => $component->getSize() === 'medium',
			'iconClass' => $component->getIconClass(),
			'isDisabled' => $component->isDisabled(),
			'iconOnly' => $component->isIconOnly(),
			'isLink' => $isLink,
			'href' => $component->getHref(),
			'fakeButtonClass' => $isLink ? (
				$component->isDisabled()
					? 'cdx-button--fake-button--disabled'
					: 'cdx-button--fake-button--enabled'
			) : '',
			'extraClasses' => $this->getExtraClasses( $component->getAttributes() ),
			'attributes' => $this->getOtherAttributes( $component->getAttributes() ),
		];

		return $this->templateParser->processTemplate( 'button', $buttonData );
	}
}
