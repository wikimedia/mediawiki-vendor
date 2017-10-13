<?php
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
 */

namespace Wikimedia\Equivset;

use Wikimedia\Equivset\Exception\EquivsetException;

/**
 * Equivset
 */
class Equivset implements \IteratorAggregate {

	/**
	 * @var string
	 */
	protected $serializedPath;

	/**
	 * @var array
	 */
	protected $data;

	/**
	 * Equivset
	 *
	 * @param array $data Equalvalent Set
	 */
	public function __construct( array $data = [] ) {
		$this->data = $data;
		$this->serializedPath = __DIR__ . '/../dist/equivset.ser';
	}

	/**
	 * Gets the equivset.
	 *
	 * @return array An associative array of equivalent characters.
	 */
	public function all() {
		if ( !$this->data ) {
			$this->data = $this->load();
		}

		return $this->data;
	}

	/**
	 * Gets the equivset.
	 *
	 * @return array An associative array of equivalent characters.
	 *
	 * @throws EquivsetException If the serialized equivset is not loaded.
	 */
	protected function load() {
		if ( !file_exists( $this->serializedPath ) ) {
			throw new EquivsetException( 'Serialized equivset is missing' );
		}

		if ( !is_readable( $this->serializedPath ) ) {
			throw new EquivsetException( 'Serialized equivset is unreadable' );
		}

		$contents = file_get_contents( $this->serializedPath );

		if ( $contents === false ) {
			throw new EquivsetException( 'Reading serialized equivset failed' );
		}

		$data = unserialize( $contents );

		if ( $data === false ) {
			throw new EquivsetException( 'Unserializing serialized equivset failed' );
		}

		return $data;
	}

	/**
	 * Normalize a string.
	 *
	 * @param string $value The string to normalize against the equivset.
	 *
	 * @return string
	 */
	public function normalize( $value ) {
		$data = $this->all();

		return strtr( $value, $data );
	}

	/**
	 * Deteremines if an equivalent character exists.
	 *
	 * @param string $key The character that was used.
	 *
	 * @return bool If the character has an equivalent.
	 */
	public function has( $key ) {
		$data = $this->all();

		return array_key_exists( $key, $data );
	}

	/**
	 * Get an equivalent character.
	 *
	 * @param string $key The character that was used.
	 *
	 * @return string The equivalent character.
	 *
	 * @throws \LogicException If character does not exist.
	 */
	public function get( $key ) {
		$data = $this->all();

		if ( ! array_key_exists( $key, $data ) ) {
			throw new \LogicException( 'Equivalent Character Not Found' );
		}

		return $data[$key];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return \Traversable The complete Equivset.
	 */
	public function getIterator() {
		return new \ArrayIterator( $this->all() );
	}

}
