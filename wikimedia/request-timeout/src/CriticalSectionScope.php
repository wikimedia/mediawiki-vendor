<?php

namespace Wikimedia\RequestTimeout;

/**
 * Class for automatically ending a critical section when a variable goes out
 * of scope.
 */
class CriticalSectionScope {
	/** @var callable|null */
	private $callback;

	/**
	 * @internal
	 * @param callable $callback
	 */
	public function __construct( $callback ) {
		$this->callback = $callback;
	}

	/**
	 * Implicitly exit the critical section
	 *
	 * @throws TimeoutException
	 */
	public function __destruct() {
		$this->exit();
	}

	/**
	 * Exit the critical section
	 *
	 * @throws TimeoutException
	 */
	public function exit() {
		if ( $this->callback ) {
			( $this->callback )();
			$this->callback = null;
		}
	}
}
