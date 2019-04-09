<?php

namespace Onoi\MessageReporter;

/**
 * Interface for objects that can report messages
 *
 * @since 1.0
 *
 * @license GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface MessageReporter {

	/**
	 * Report the provided message
	 *
	 * @param string $message
	 */
	public function reportMessage( $message );

}
