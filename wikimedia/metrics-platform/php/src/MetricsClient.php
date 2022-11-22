<?php

namespace Wikimedia\MetricsPlatform;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfig;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfigException;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfigFactory;

class MetricsClient {

	/**
	 * The ID of v1.0.0 of the mediawiki/client/metrics_event schema in the schemas/event/secondary
	 * repository.
	 *
	 * @var string
	 */
	private const METRICS_PLATFORM_SCHEMA = '/analytics/mediawiki/client/metrics_event/1.0.0';

	/** @var Integration */
	private $integration;

	/** @var ContextController */
	private $contextController;

	/** @var CurationController */
	private $curationController;

	/** @var StreamConfigFactory */
	private $streamConfigFactory;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * MetricsClient constructor.
	 *
	 * @param Integration $integration
	 * @param StreamConfigFactory $streamConfigFactory
	 * @param ?LoggerInterface $logger
	 * @param ?ContextController $contextController
	 * @param ?CurationController $curationController
	 */
	public function __construct(
		Integration $integration,
		StreamConfigFactory $streamConfigFactory,
		?LoggerInterface $logger = null,
		?ContextController $contextController = null,
		?CurationController $curationController = null
	) {
		$this->integration = $integration;
		$this->streamConfigFactory = $streamConfigFactory;
		$this->logger = $logger ?? new NullLogger();
		$this->contextController = $contextController ?? new ContextController( $integration );
		$this->curationController = $curationController ?? new CurationController();
	}

	/**
	 * Try to submit an event according to the configuration of the given stream.
	 *
	 * An event (E) will be submitted to stream (S) if:
	 *
	 * 1. E has the $schema property set;
	 * 2. S has a valid configuration
	 * 3. E passes the configured curation rules for S
	 *
	 * @param string $streamName
	 * @param array $event
	 * @return bool true if the event was submitted, otherwise false
	 */
	public function submit( string $streamName, array $event ): bool {
		if ( !isset( $event['$schema'] ) ) {
			$this->logger->warning(
				'The event submitted to stream {streamName} is missing the required "$schema" property: {event}',
				[
					'streamName' => $streamName,
					'event' => $event,
				]
			);

			return false;
		}
		try {
			$streamConfig = $this->streamConfigFactory->getStreamConfig( $streamName );
		} catch ( StreamConfigException $e ) {
			$this->logger->warning(
				'The configuration for stream {streamName} is invalid: {validationError}',
				[
					'streamName' => $streamName,
					'validationError' => $e->getMessage(),
				]
			);

			return false;
		}

		return $this->submitInternal( $streamName, $streamConfig, $event );
	}

	/**
	 * @param string $streamName
	 * @param StreamConfig $streamConfig
	 * @param array $event
	 * @param string|null $dt
	 * @return bool
	 */
	private function submitInternal(
		string $streamName,
		StreamConfig $streamConfig,
		array $event,
		string $dt = null
	): bool {
		$event = $this->prepareEvent( $streamName, $event, $dt );
		$event = $this->contextController->addRequestedValues( $event, $streamConfig );

		if ( $this->curationController->shouldProduceEvent( $event, $streamConfig ) ) {
			$this->integration->send( $event );

			return true;
		}

		return false;
	}

	/**
	 * Prepares the event with extra data for submission.
	 *
	 * This will always set
	 * - `meta.stream` to `$streamName`
	 *
	 * If `client_dt` is in the event, then this will always unset `dt`. If `client_dt` is not in
	 * the event, then `dt` will be set to the given time or the current time.
	 *
	 * @param string $streamName
	 * @param array $event
	 * @param string|null $dt
	 * @return array
	 */
	private function prepareEvent( string $streamName, array $event, string $dt = null ): array {
		$requiredData = [
			// meta.stream should always be set to $streamName
			'meta' => [
				'stream' => $streamName
			]
		];

		$preparedEvent = array_merge_recursive(
			self::getEventDefaults(),
			$event,
			$requiredData
		);

		//
		// If this is a migrated legacy event, client_dt will have been set already by
		// EventLogging::encapsulate, and the dt field should be left unset so that it can be set
		// to the intake time by EventGate. If dt was set by a caller, we unset it here.
		//
		// If client_dt is absent, this schema is native to the Event Platform, and dt represents
		// the client-side event time. We set it here, overwriting any caller-provided value to
		// ensure consistency.
		//
		// https://phabricator.wikimedia.org/T277253
		// https://phabricator.wikimedia.org/T277330
		//
		if ( isset( $preparedEvent['client_dt'] ) ) {
			unset( $preparedEvent['dt'] );
		} else {
			$preparedEvent['dt'] = $dt ?? $this->getTimestamp();
		}

		return $preparedEvent;
	}

	/**
	 * Get an ISO 8601 timestamp for the current time, e.g. 2022-05-03T14:00:41.000Z.
	 *
	 * Note well that the timestamp contains milliseconds for consistency with other Metrics
	 * Platform client implementations.
	 *
	 * @return string
	 */
	private function getTimestamp(): string {
		return gmdate( 'Y-m-d\TH:i:s.v\Z' );
	}

	/**
	 * Returns values we always want set in events based on common
	 * schemas for all EventLogging events.  This sets:
	 *
	 * - meta.domain to the value of $config->get( 'ServerName' )
	 * - http.request_headers['user-agent'] to the value of $_SERVER( 'HTTP_USER_AGENT' ) ?? ''
	 *
	 * The returned object will be used as default values for the $event params passed
	 * to submit().
	 *
	 * @return array
	 */
	private function getEventDefaults(): array {
		return [
			'meta' => [
				'domain' => $this->integration->getHostName(),
			],
			'http' => [
				'request_headers' => [
					'user-agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
				]
			]
		];
	}

	/**
	 * Constructs a "Metrics Platform Event" event given the event name and custom data. The event
	 * is submitted to all streams that is interested in the event.
	 *
	 * An event (E) is constructed for a stream (S) by:
	 *
	 * 1. Initializing the minimum valid event E that can be submitted to S
	 * 2. If it is given, adding the formatted custom data as the `custom_data` property of E
	 * 3. Mixing the context attributes requested in the configuration for S into E
	 *
	 * After which, E is submitted to S.
	 *
	 * Note well that all events are submitted with the same client-side event timestamp (the `dt`
	 * property) for consistency.
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform
	 *
	 * @param string $eventName
	 * @param array $customData
	 */
	public function dispatch( string $eventName, array $customData = [] ): void {
		$customData = $this->formatCustomData( $customData );
		$timestamp = $this->getTimestamp();

		$streamNames = $this->streamConfigFactory->getStreamNamesForEvent( $eventName );

		foreach ( $streamNames as $streamName ) {
			$streamConfig = $this->streamConfigFactory->getStreamConfig( $streamName );
			$event = [
				'$schema' => self::METRICS_PLATFORM_SCHEMA,
				'name' => $eventName,
			];

			if ( $customData ) {
				$event['custom_data'] = $customData;
			}

			$this->submitInternal( $streamName, $streamConfig, $event, $timestamp );
		}
	}

	/**
	 * @param array $customData
	 * @return array
	 */
	private function formatCustomData( array $customData ): array {
		return array_map( static function ( $value ) {
			$type = strtolower( gettype( $value ) );

			// TODO: Should the JavaScript impl. be updated to distinguish between integers and
			// floating-point numbers?
			if ( $type === 'integer' || $type === 'double' ) {
				$type = 'number';
			} elseif ( $type === 'boolean' ) {
				$value = $value ? 'true' : 'false';
			} elseif ( $type === 'null' ) {
				$value = 'null';
			}

			return [
				'data_type' => $type,
				'value' => (string)$value,
			];
		}, $customData );
	}
}
