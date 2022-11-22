<?php

namespace Wikimedia\MetricsPlatform;

interface Integration {

	/**
	 * Get the hostname associated with the current request.
	 *
	 * @return string
	 */
	public function getHostName(): string;

	/**
	 * Transmit an event to a destination intake service.
	 *
	 * @param array $event event data, represented as an associative array
	 */
	public function send( array $event ): void;

	/**
	 * Gets the value of a context attribute by name, e.g. performer_id.
	 *
	 * See `php/src/StreamConfig/data/metrics_platform_client.schema.json` for a list of the
	 * valid context attribute names.
	 *
	 * @param string $name
	 * @return mixed|null
	 */
	public function getContextAttribute( string $name );
}
