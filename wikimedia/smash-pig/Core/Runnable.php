<?php namespace SmashPig\Core;

/**
 * A simple Command pattern module that can receive an "execute" message.  Used
 * to mark classes which are used as a container to a generic PHP-executable
 * service.
 *
 * This is the only interface required when writing a stored job processor, for example.
 */
interface Runnable {
	/**
	 * Do whatever it is that you do.
	 */
	public function execute();
}
