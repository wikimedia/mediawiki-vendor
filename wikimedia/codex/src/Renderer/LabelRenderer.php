<?php
declare( strict_types = 1 );

/**
 * LabelRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `LabelRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\Label;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\ILocalizer;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * LabelRenderer is responsible for rendering the HTML markup
 * for a Label component using a Mustache template.
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
class LabelRenderer extends Renderer {

	/**
	 * Constructor to initialize the LabelRenderer with a sanitizer and a template parser.
	 *
	 * @since 0.1.0
	 * @param Sanitizer $sanitizer The sanitizer instance used for content sanitization.
	 * @param TemplateParser $templateParser The template parser instance.
	 * @param ILocalizer $localizer The localizer instance used for i18n messages.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		private readonly TemplateParser $templateParser,
		private readonly ILocalizer $localizer
	) {
		parent::__construct( $sanitizer );
	}

	/**
	 * Renders the HTML for a label component.
	 *
	 * Uses the provided Label component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The Label component to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof Label ) {
			throw new InvalidArgumentException( "Expected instance of Label, got " . get_class( $component ) );
		}

		$labelData = [
			'id' => $component->getId(),
			'isLegend' => $component->isLegend(),
			'inputId' => $component->getInputId(),
			'labelText-html' => $this->sanitizer->sanitizeText( $component->getLabelText() ),
			'optionalFlag' => $component->isOptional() ?
				$this->localizer->msg( 'cdx-label-optional-flag' ) :
				null,
			'description-html' => $this->sanitizer->sanitizeText( $component->getDescription() ),
			'descriptionId' => $component->getDescriptionId(),
			'icon' => $component->getIconClass() ?? '',
			'isVisuallyHidden' => $component->isVisuallyHidden(),
			'isDisabled' => $component->isDisabled(),
			'extraClasses' => $this->getExtraClasses( $component->getAttributes() ),
			'attributes' => $this->getOtherAttributes( $component->getAttributes() ),
		];

		return $this->templateParser->processTemplate( 'label', $labelData );
	}
}
