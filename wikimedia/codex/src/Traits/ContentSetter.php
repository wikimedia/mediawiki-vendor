<?php
declare( strict_types = 1 );

/**
 * ContentSetter.php
 *
 * @category Traits
 * @package  Codex\Traits
 * @since    0.7.2
 * @author   Roan Kattouw <roan.kattouw@gmail.com>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/latest/ Codex Documentation
 */

namespace Wikimedia\Codex\Traits;

use Wikimedia\Codex\Component\HtmlSnippet;

/**
 * ContentSetter is a utility trait that adds the setContentText() and setContentHtml() methods
 * to classes that already have a setContent() method, for backwards compatibility.
 */
trait ContentSetter {
	abstract public function setContent( string|HtmlSnippet $content ): self;

	/**
	 * @deprecated since 0.7.2, use setContent instead
	 */
	public function setContentText( string $content ): self {
		return $this->setContent( $content );
	}

	/**
	 * @deprecated since 0.7.2, use setContent instead
	 */
	public function setContentHtml( HtmlSnippet $content ): self {
		return $this->setContent( $content );
	}
}
