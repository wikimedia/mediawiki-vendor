<?php
declare( strict_types = 1 );

/**
 * TextAreaRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `TextAreaRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\TextArea;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * TextAreaRenderer is responsible for rendering the HTML markup
 * for a TextArea component using a Mustache template.
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
class TextAreaRenderer extends Renderer {

	/**
	 * The template parser instance.
	 */
	private TemplateParser $templateParser;

	/**
	 * Constructor to initialize the TextAreaRenderer with a sanitizer and a template parser.
	 *
	 * @since 0.1.0
	 * @param Sanitizer $sanitizer The sanitizer instance used for content sanitization.
	 * @param TemplateParser $templateParser The template parser instance.
	 */
	public function __construct( Sanitizer $sanitizer, TemplateParser $templateParser ) {
		parent::__construct( $sanitizer );
		$this->templateParser = $templateParser;
	}

	/**
	 * Renders the HTML for a textarea component.
	 *
	 * Uses the provided TextArea component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The TextArea component to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof TextArea ) {
			throw new InvalidArgumentException( "Expected instance of TextArea, got " . get_class( $component ) );
		}

		$textareaData = [
			'id' => $component->getInputId(),
			'name' => $component->getName(),
			'placeholder' => $component->getPlaceholder(),
			'value' => $component->getValue(),
			'isDisabled' => $component->isDisabled(),
			'isReadonly' => $component->isReadonly(),
			'hasStartIcon' => $component->hasStartIcon(),
			'hasEndIcon' => $component->hasEndIcon(),
			'startIconClass' => $component->getStartIconClass(),
			'endIconClass' => $component->getEndIconClass(),
			'status' => $component->getStatus(),
			'inputExtraClasses' => $this->getExtraClasses( $component->getInputAttributes() ),
			'inputAttributes' => $this->getOtherAttributes( $component->getInputAttributes() ),
			'wrapperExtraClasses' => $this->getExtraClasses( $component->getWrapperAttributes() ),
			'wrapperAttributes' => $this->getOtherAttributes( $component->getWrapperAttributes() ),
		];

		return $this->templateParser->processTemplate( 'text-area', $textareaData );
	}
}
