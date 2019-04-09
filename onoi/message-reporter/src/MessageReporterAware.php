<?php

namespace Onoi\MessageReporter;

/**
 * @since 1.2
 *
 * @license GNU GPL v2+
 * @author mwjames
 */
interface MessageReporterAware {

	/**
	 * Allows to inject a MessageReporter and make an object aware of its
	 * existence.
	 */
	public function setMessageReporter( MessageReporter $messageReporter );

}
