<?php

namespace Onoi\MessageReporter;

/**
 * @since 1.3
 *
 * @license GNU GPL v2+
 * @author mwjames
 */
trait MessageReporterAwareTrait {

	/**
	 * @var MessageReporter
	 */
	protected $messageReporter;

	public function setMessageReporter( MessageReporter $messageReporter ) {
		$this->messageReporter = $messageReporter;
	}

}
