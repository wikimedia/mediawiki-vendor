<?php
declare( strict_types = 1 );

/**
 * SelectRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `SelectRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\Select;
use Wikimedia\Codex\Contract\Component;
use Wikimedia\Codex\Contract\Renderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * SelectRenderer is responsible for rendering the HTML markup
 * for a Select component using a Mustache template.
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
class SelectRenderer extends Renderer {

	/**
	 * The template parser instance.
	 */
	private TemplateParser $templateParser;

	/**
	 * Constructor to initialize the SelectRenderer with a sanitizer and a template parser.
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
	 * Renders the HTML for a select dropdown component.
	 *
	 * Uses the provided Select component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Component $component The Select object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( Component $component ): string {
		if ( !$component instanceof Select ) {
			throw new InvalidArgumentException( "Expected instance of Select, got " . get_class( $component ) );
		}

		$selectData = [
			'id' => $component->getId(),
			'isDisabled' => $component->isDisabled(),
			'selectedOption' => $component->getSelectedOption(),
			'extraClasses' => $this->getExtraClasses( $component->getAttributes() ),
			'attributes' => $this->getOtherAttributes( $component->getAttributes() ),
			'options' => $this->prepareOptions( $component->getOptions() ),
			'optGroups' => $this->prepareOptGroups( $component->getOptGroups() ),
		];

		return $this->templateParser->processTemplate( 'select', $selectData );
	}

	/**
	 * Prepare options for rendering.
	 *
	 * @since 0.1.0
	 * @param array $options An array of options, like from Select::getOptions()
	 * @return array An array of normalized option data for rendering.
	 */
	private function prepareOptions( array $options ): array {
		$newOptions = [];
		foreach ( $options as $key => $option ) {
			if ( is_string( $key ) ) {
				$newOptions[] = [
					'value' => $key,
					'text' => $option,
					'selected' => false,
				];
			} elseif ( is_array( $option ) ) {
				$newOptions[] = [
					'value' => $option['value'],
					'text' => $option['text'],
					'selected' => $option['selected'] ?? false,
				];
			}
		}

		return $newOptions;
	}

	/**
	 * Prepare optGroups for rendering.
	 *
	 * @since 0.1.0
	 * @param array $optGroups Array of option groups, from Select::getOptGroups()
	 * @return array Prepared array of optGroups with their respective options for rendering.
	 */
	private function prepareOptGroups( array $optGroups ): array {
		$newOptGroups = [];
		foreach ( $optGroups as $label => $groupOptions ) {
			$newOptGroups[] = [
				'label' => $label,
				'options' => $this->prepareOptions( $groupOptions ),
			];
		}

		return $newOptGroups;
	}
}
