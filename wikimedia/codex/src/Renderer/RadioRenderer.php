<?php
declare( strict_types = 1 );

/**
 * RadioRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `RadioRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\Radio;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\ILocalizer;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * RadioRenderer is responsible for rendering the HTML markup
 * for a Radio component using a Mustache template.
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
class RadioRenderer extends Renderer {

	/**
	 * Constructor to initialize the RadioBarRenderer with a sanitizer and a template parser.
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
	 * Renders the HTML for a radio component.
	 *
	 * Uses the provided Radio component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The Radio component to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof Radio ) {
			throw new InvalidArgumentException( "Expected instance of Radio, got " . get_class( $component ) );
		}

		$label = $component->getLabel();
		$labelData = null;

		if ( $label ) {
			$labelData = [
				'id' => $label->getId(),
				'coreClass' => 'cdx-radio__label',
				'labelText-html' => $this->sanitizer->sanitizeText( $label->getLabelText() ),
				'optionalFlag' => $label->isOptional() ?
					$this->localizer->msg( 'cdx-label-optional-flag' ) :
					null,
				'isVisuallyHidden' => $label->isVisuallyHidden(),
				'inputId' => $component->getInputId(),
				'description-html' => $this->sanitizer->sanitizeText( $label->getDescription() ),
				'descriptionId' => $label->getDescriptionId(),
				'isDisabled' => $label->isDisabled(),
				'iconClass' => $label->getIconClass(),
				'extraClasses' => $this->getExtraClasses( $label->getAttributes() ),
				'attributes' => $this->getOtherAttributes( $label->getAttributes() ),
			];
		}

		$radioData = [
			'name' => $component->getName(),
			'value' => $component->getValue(),
			'inputId' => $component->getInputId(),
			'isChecked' => $component->isChecked(),
			'isDisabled' => $component->isDisabled(),
			'isInline' => $component->isInline(),
			'ariaDescribedby' => $label?->getDescriptionId(),
			'inputExtraClasses' => $this->getExtraClasses( $component->getInputAttributes() ),
			'inputAttributes' => $this->getOtherAttributes( $component->getInputAttributes() ),
			'wrapperExtraClasses' => $this->getExtraClasses( $component->getWrapperAttributes() ),
			'wrapperAttributes' => $this->getOtherAttributes( $component->getWrapperAttributes() ),
			'label' => $labelData,
		];

		return $this->templateParser->processTemplate( 'radio', $radioData );
	}
}
