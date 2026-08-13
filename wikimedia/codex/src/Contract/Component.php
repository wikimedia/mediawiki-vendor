<?php
declare( strict_types = 1 );

namespace Wikimedia\Codex\Contract;

use Stringable;

abstract class Component implements Stringable {
	protected function __construct( protected Renderer $renderer ) {
	}

	/**
	 * Get the component's HTML representation.
	 *
	 * This method generates the HTML markup for the component, incorporating relevant properties
	 * and any additional attributes. The component is structured using appropriate HTML elements
	 * as defined by the implementation.
	 *
	 * @since 0.8.0
	 * @return string The generated HTML string for the component.
	 */
	public function getHtml(): string {
		return $this->renderer->render( $this );
	}

	public function __toString(): string {
		return $this->getHtml();
	}

	/**
	 * Backwards compatibility for the pre-0.8 API.
	 * @deprecated
	 * @return static
	 */
	public function build() {
		return $this;
	}
}
