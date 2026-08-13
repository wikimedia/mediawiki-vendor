<?php
declare( strict_types = 1 );

/**
 * TextInputRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `TextInputRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\TextInput;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * TextInputRenderer is responsible for rendering the HTML markup
 * for a TextInput component using a Mustache template.
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
class TextInputRenderer extends Renderer {

	/**
	 * The template parser instance.
	 */
	private TemplateParser $templateParser;

	/**
	 * Constructor to initialize the TextInputRenderer with a sanitizer and a template parser.
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
	 * Renders the HTML for a text input component.
	 *
	 * Uses the provided TextInput component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The TextInput component to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof TextInput ) {
			throw new InvalidArgumentException( "Expected instance of TextInput, got " . get_class( $component ) );
		}

		$textInputData = [
			'inputId' => $component->getInputId(),
			'type' => $component->getType(),
			'name' => $component->getName(),
			'isDisabled' => $component->isDisabled(),
			'value' => $component->getValue(),
			'placeholder' => $component->getPlaceholder(),
			'hasStartIcon' => $component->hasStartIcon(),
			'startIconClass' => $component->getStartIconClass(),
			'hasEndIcon' => $component->hasEndIcon(),
			'endIconClass' => $component->getEndIconClass(),
			'status' => $component->getStatus(),
			'inputExtraClasses' => $this->getExtraClasses( $component->getInputAttributes() ),
			'inputAttributes' => $this->getOtherAttributes( $component->getInputAttributes() ),
			'wrapperExtraClasses' => $this->getExtraClasses( $component->getWrapperAttributes() ),
			'wrapperAttributes' => $this->getOtherAttributes( $component->getWrapperAttributes() ),
		];

		return $this->templateParser->processTemplate( 'text-input', $textInputData );
	}
}
