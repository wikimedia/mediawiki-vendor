<?php

namespace Wikimedia\MetricsPlatform;

interface Integration {

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
