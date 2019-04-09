<?php

namespace Onoi\MessageReporter;

/**
 * @since 1.0
 *
 * @license GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class NullMessageReporter implements MessageReporter {

	/**
	 * @since 1.0
	 *
	 * {@inheritDoc}
	 */
	public function reportMessage( $message ) { }

}
