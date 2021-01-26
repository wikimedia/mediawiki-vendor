<?php

namespace Wikimedia\RequestTimeout;

/**
 * A class providing a named critical section concept. When the code is inside
 * a critical section, if a request timeout occurs, it is queued and then
 * delivered when the critical section exits.
 *
 * The class stores configuration for "emergency timeouts". This is a second
 * timeout which limits the amount of time a critical section may be open.
 */
class CriticalSectionProvider {
	/** @var RequestTimeout */
	private $requestTimeout;

	/** @var float */
	private $emergencyLimit;

	/** @var callable|null */
	private $emergencyCallback;

	/** @var array */
	private $stack = [];

	/**
	 * @internal Use RequestTimeout::createCriticalSectionProvider()
	 *
	 * @param RequestTimeout $requestTimeout The parent object
	 * @param float $emergencyLimit The emergency timeout in seconds
	 * @param callable|null $emergencyCallback A callback to call when the
	 *   emergency timeout expires. If null, an exception will be thrown.
	 */
	public function __construct(
		RequestTimeout $requestTimeout,
		$emergencyLimit,
		$emergencyCallback
	) {
		$this->requestTimeout = $requestTimeout;
		$this->emergencyLimit = $emergencyLimit;
		$this->emergencyCallback = $emergencyCallback;
	}

	/**
	 * Enter a critical section, giving it a name. The name should uniquely
	 * identify the calling code.
	 *
	 * Multiple critical sections may be active at a given time. Critical
	 * sections created by this method must be exited in the reverse order to
	 * which they were created, i.e. there is a stack of named critical
	 * sections.
	 *
	 * @param string $name
	 */
	public function enter( $name ) {
		$id = $this->requestTimeout->enterCriticalSection(
			$name, $this->emergencyLimit, $this->emergencyCallback );
		$this->stack[ count( $this->stack ) ] = [
			'name' => $name,
			'id' => $id
		];
	}

	/**
	 * Exit a named critical section. If the name does not match the most recent
	 * call to enter(), an exception will be thrown.
	 *
	 * @throws CriticalSectionMismatchException
	 * @throws TimeoutException
	 * @param string $name
	 */
	public function exit( $name ) {
		$i = count( $this->stack ) - 1;
		if ( $i === -1 ) {
			throw new CriticalSectionMismatchException( $name, '[none]' );
		}
		if ( $this->stack[$i]['name'] !== $name ) {
			throw new CriticalSectionMismatchException( $name, $this->stack[$i]['name'] );
		}
		$this->requestTimeout->exitCriticalSection( $this->stack[$i]['id'] );
		unset( $this->stack[$i] );
	}

	/**
	 * Enter a critical section, and return a scope variable. The critical
	 * section will automatically exit when the scope variable is destroyed.
	 *
	 * Multiple critical sections may be active at a given time. There is no
	 * restriction on the order in which critical sections created by this
	 * method are exited.
	 *
	 * NOTE: Callers should typically call CriticalSectionScope::exit() instead
	 * of waiting for __destruct() to be called, since exiting a critical
	 * section may throw a timeout exception, but it is a fatal error to throw
	 * an exception from a destructor during request shutdown.
	 *
	 * @param string $name A name for the critical section, used in error messages
	 * @return CriticalSectionScope
	 */
	public function scopedEnter( $name ) {
		$id = $this->requestTimeout->enterCriticalSection( $name,
			$this->emergencyLimit, $this->emergencyCallback );

		return new CriticalSectionScope(
			function () use ( $id ) {
				$this->requestTimeout->exitCriticalSection( $id );
			}
		);
	}
}
