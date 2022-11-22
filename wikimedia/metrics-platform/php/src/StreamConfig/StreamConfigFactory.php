<?php

namespace Wikimedia\MetricsPlatform\StreamConfig;

class StreamConfigFactory {

	/** @var array|false */
	protected $rawStreamConfigs;

	/** @var array<string,string[]> */
	private $eventToStreamNamesMap;

	/**
	 * @param array|false $rawStreamConfigs
	 */
	public function __construct( $rawStreamConfigs ) {
		$this->rawStreamConfigs = $rawStreamConfigs;
	}

	/**
	 * Gets the configuration for the given stream.
	 *
	 * Note well that if the raw stream configuration is falsy, then this will always return
	 * an empty stream configuration.
	 *
	 * @param string $streamName
	 * @return StreamConfig
	 * @throws StreamConfigException If the given stream is not configured
	 * @throws StreamConfigException If the given stream configuration is not an ordered dictionary
	 */
	public function getStreamConfig( string $streamName ): StreamConfig {
		if ( $this->rawStreamConfigs === false ) {
			return new StreamConfig( [] );
		}

		if (
			!isset( $this->rawStreamConfigs[ $streamName ] )
			|| !is_array( $this->rawStreamConfigs[ $streamName ] )
		) {
			throw new StreamConfigException( 'The stream configuration is not defined or is not an array.' );
		}

		return new StreamConfig( $this->rawStreamConfigs[$streamName] );
	}

	/**
	 * @param string $eventName
	 * @return array
	 */
	public function getStreamNamesForEvent( string $eventName ): array {
		$result = [];

		foreach ( $this->getEventToStreamNamesMap() as $key => $streamNames ) {
			if ( strpos( $eventName, $key ) === 0 ) {
				$result = array_merge( $result, $streamNames );
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	private function getEventToStreamNamesMap(): array {
		if ( $this->rawStreamConfigs === false ) {
			return [];
		}

		if ( $this->eventToStreamNamesMap !== null ) {
			return $this->eventToStreamNamesMap;
		}

		$this->eventToStreamNamesMap = [];

		foreach ( $this->rawStreamConfigs as $streamName => $rawStreamConfig ) {
			if (
				!isset( $rawStreamConfig['producers'] )
				|| !isset( $rawStreamConfig['producers']['metrics_platform_client'] )
				|| !isset( $rawStreamConfig['producers']['metrics_platform_client']['events'] )
			) {
				continue;
			}

			$events = (array)$rawStreamConfig['producers']['metrics_platform_client']['events'];

			foreach ( $events as $event ) {
				if ( !isset( $this->eventToStreamNamesMap[$event] ) ) {
					$this->eventToStreamNamesMap[$event] = [];
				}

				$this->eventToStreamNamesMap[$event][] = $streamName;
			}
		}

		return $this->eventToStreamNamesMap;
	}
}
