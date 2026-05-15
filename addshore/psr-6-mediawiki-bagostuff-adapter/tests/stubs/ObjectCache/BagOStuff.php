<?php

namespace Wikimedia\ObjectCache;

class BagOStuff {

	/**
	 * @var array<string,mixed>
	 */
	private array $store = [];

	public function makeKey( mixed ...$components ): string {
		return implode( ':', array_map( 'strval', $components ) );
	}

	public function get( string $key ): mixed {
		return array_key_exists( $key, $this->store ) ? $this->store[$key] : false;
	}

	public function set( string $key, mixed $value, int $exptime = 0 ): bool {
		$this->store[$key] = $value;

		return true;
	}

	public function delete( string $key ): bool {
		unset( $this->store[$key] );

		return true;
	}

}