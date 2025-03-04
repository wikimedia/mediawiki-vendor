<?php
namespace SmashPig\Core\SequenceGenerators;

interface ISequenceGenerator {

	/**
	 * Get the next number in the sequence
	 *
	 * @return int
	 */
	public function getNext();

	/**
	 * Initialize the sequence to the given number
	 *
	 * @param int $startNumber
	 */
	public function initializeSequence( $startNumber );
}
