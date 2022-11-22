<?php

namespace Wikimedia\MetricsPlatform;

use Wikimedia\MetricsPlatform\StreamConfig\StreamConfig;

class ContextController {

	/** @var Integration */
	private $integration;

	/**
	 * ContextController constructor.
	 * @param Integration $integration
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Add context attributes configured in the stream configuration.
	 *
	 * @param array $event
	 * @param StreamConfig $streamConfig
	 * @return array
	 */
	public function addRequestedValues( array $event, StreamConfig $streamConfig ): array {
		$requestedValues = $streamConfig->getRequestedValues();

		foreach ( $requestedValues as $requestedValue ) {
			list( $primaryKey, $secondaryKey ) = explode( '_', $requestedValue, 2 );

			$value = $this->integration->getContextAttribute( $requestedValue );

			// Context attributes are null by default. Only add the requested context attribute
			// - incurring the cost of transporting it - if it is not null.
			if ( $value === null ) {
				continue;
			}

			if ( !isset( $event[$primaryKey] ) ) {
				$event[$primaryKey] = [];
			}

			$event[$primaryKey][$secondaryKey] = $value;
		}

		return $event;
	}
}
