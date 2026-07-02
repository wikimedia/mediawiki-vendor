<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

/**
 * A command without file handling.
 *
 * This is almost the same as Command, except with a type-hinted executor.
 */
class UnboxedCommand extends Command {
	/**
	 * External callers should typically use UnboxedExecutor::createCommand()
	 */
	public function __construct( protected readonly UnboxedExecutor $executor ) {
	}

	/**
	 * Execute the command with the current executor
	 *
	 * @return UnboxedResult
	 */
	public function execute() {
		return $this->executor->execute( $this );
	}
}
