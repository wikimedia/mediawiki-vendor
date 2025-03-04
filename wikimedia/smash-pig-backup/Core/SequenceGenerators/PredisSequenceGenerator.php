<?php

namespace SmashPig\Core\SequenceGenerators;

use Predis\Client;
use SmashPig\Core\SmashPigException;

class PredisSequenceGenerator implements ISequenceGenerator {

	protected $name;
	protected $servers;
	protected $redis_options = [];
	const SEQ_KEY_PREFIX = 'sequence_';

	/**
	 * @var Client|null
	 */
	protected $connection = null;

	/**
	 * @param array $options should include at least 'sequence' and 'servers'
	 *  redis_options is an optional array of options to pass to Predis\Client.
	 * @throws SmashPigException
	 */
	public function __construct( $options ) {
		$this->name = $options['sequence'];
		if ( empty( $options['servers'] ) ) {
			throw new SmashPigException( 'No servers specified' );
		} else {
			$this->servers = $options['servers'];
		}
		if ( !empty( $options['redis_options'] ) && is_array( $options['redis_options'] ) ) {
			$this->redis_options = array_merge( $this->redis_options, $options['redis_options'] );
		}
	}

	protected function getKey() {
		return self::SEQ_KEY_PREFIX . $this->name;
	}

	/**
	 * Get the next number in the sequence
	 *
	 * @return int
	 */
	public function getNext() {
		if ( !$this->connection ) {
			$this->connect();
		}
		return $this->connection->incr( $this->getKey() );
	}

	protected function connect() {
		$this->connection = new Client( $this->servers, $this->redis_options );
	}

	/**
	 * Initialize the sequence to the given number
	 *
	 * @param int $startNumber
	 */
	public function initializeSequence( $startNumber ) {
		if ( !$this->connection ) {
			$this->connect();
		}
		$this->connection->set( $this->getKey(), $startNumber );
	}
}
