<?php
declare( strict_types=1 );

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace Wikimedia\JsonCodec;

use Stringable;

/**
 * An abbreviation is a short name for a hint.  It is in a separate
 * namespace from `class-string` and so we give it a type-safe wrapper.
 * @template T
 */
class Abbrev implements Stringable {
	/**
	 * Create a new abbreviation.
	 *
	 * Abbreviations need to be registered to be used.
	 * @param string $name
	 * @param class-string<T>|Hint<T> $hint
	 */
	public function __construct(
		/**
		 * The abbreviation name. This is typically prefixed with `@`
		 * by the codec if it appears in the output, but no prefix should
		 * be present here.
		 */
		public readonly string $name,
		/**
		 * The Hint corresponding to this abbreviation.
		 * @var class-string<T>|Hint<T>
		 */
		public readonly string|Hint $hint,
	) {
	}

	/**
	 * Return `true` is this abbreviation is the same as $a.
	 */
	public function isSameAs( Abbrev $a ): bool {
		return $this->name === $a->name && Hint::isSame( $this->hint, $a->hint );
	}

	public function __toString(): string {
		return "@{$this->name}={$this->hint}";
	}
}
