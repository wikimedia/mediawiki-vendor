<?php
declare( strict_types = 1 );

/**
 * AccordionRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `AccordionRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\Accordion;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * AccordionRenderer is responsible for rendering the HTML markup
 * for an Accordion component using a Mustache template.
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
class AccordionRenderer extends Renderer {

	/**
	 * The template parser instance.
	 */
	private TemplateParser $templateParser;

	/**
	 * Constructor to initialize the AccordionRenderer with a sanitizer and a template parser.
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
	 * Renders the HTML for an accordion component.
	 *
	 * Uses the provided Accordion component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The Accordion object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof Accordion ) {
			throw new InvalidArgumentException( "Expected instance of Accordion, got " . get_class( $component ) );
		}

		$accordionData = [
			'id' => $component->getId(),
			'title-html' => $this->sanitizer->sanitizeText( $component->getTitle() ),
			'description-html' => $this->sanitizer->sanitizeText( $component->getDescription() ),
			'content-html' => $this->sanitizer->sanitizeText( $component->getContent() ),
			'isOpen' => $component->isOpen(),
			'extraClasses' => $this->getExtraClasses( $component->getAttributes() ),
			'separation' => $component->getSeparation() === 'none' ? null : $component->getSeparation(),
			'attributes' => $this->getOtherAttributes( $component->getAttributes() ),
		];

		return $this->templateParser->processTemplate( 'accordion', $accordionData );
	}
}
