<?php

namespace SmashPig\Core\Logging\LogStreams;

use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;

class NullLogStream implements ILogStream {

	public function __construct() {
		// need an empty constructor to allow config to instantiate
	}

	public function processEvent( LogEvent $event ) {
		// do nothing
	}

	public function registerContextHandler( LogContextHandler $ch ) {
		// twiddle thumbs
	}

	public function enterContext( $contextNames ) {
		// whistle tunelessly, tap feet
	}

	public function renameContext( $contextNames, $oldTopName ) {
		// play solitaire
	}

	public function leaveContext( $contextNames ) {
		// ...or snake
	}

	public function shutdown() {
		// remember when every phone came with snake?
	}
}
