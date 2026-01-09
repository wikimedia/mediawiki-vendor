<?php

namespace Wikimedia\MetricsPlatform;

use Wikimedia\MetricsPlatform\StreamConfig\StreamConfig;

class ContextController {

	public function __construct(
		private readonly Integration $integration
	) {
	}

	/**
	 * Add context attributes configured in the stream configuration.
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/Contextual_attributes
	 */
	public function addRequestedValues( array $event, StreamConfig $streamConfig ): array {
		$requestedValues = $streamConfig->getRequestedValues();
		$requestedValues = array_unique(
			array_merge( $requestedValues, [
				'agent_client_platform',
				'agent_client_platform_family',
			] )
		);

		foreach ( $requestedValues as $requestedValue ) {
			$value = $this->integration->getContextAttribute( $requestedValue );

			// Context attributes are null by default. Only add the requested context attribute
			// - incurring the cost of transporting it - if it is not null.
			if ( $value === null ) {
				continue;
			}

			[ $primaryKey, $secondaryKey ] = explode( '_', $requestedValue, 2 );

			if ( !isset( $event[$primaryKey] ) ) {
				$event[$primaryKey] = [];
			}

			$event[$primaryKey][$secondaryKey] = $value;
		}

		return $event;
	}
}
