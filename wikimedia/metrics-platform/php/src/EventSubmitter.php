<?php

namespace Wikimedia\MetricsPlatform;

interface EventSubmitter {

	/**
	 * Submit an event according to the configuration of the given stream.
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Event_Platform
	 * @see https://wikitech.wikimedia.org/wiki/Event_Platform/Instrumentation_How_To#In_PHP
	 */
	public function submit( string $streamName, array $event ): void;
}
