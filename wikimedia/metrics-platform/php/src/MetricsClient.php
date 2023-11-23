<?php

namespace Wikimedia\MetricsPlatform;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfigFactory;

class MetricsClient implements LoggerAwareInterface {
	use LoggerAwareTrait;
	use InteractionDataTrait;

	/**
	 * The ID of the mediawiki/client/metrics_event schema in the schemas/event/secondary
	 * repository.
	 *
	 * @deprecated
	 *
	 * @var string
	 */
	public const SCHEMA = '/analytics/mediawiki/client/metrics_event/2.0.0';

	/**
	 * The ID of the mediawiki/client/metrics_event schema in the schemas/event/secondary
	 * repository.
	 *
	 * @var string
	 */
	public const MONO_SCHEMA = '/analytics/mediawiki/client/metrics_event/2.0.0';

	/**
	 * The ID of the Metrics Platform base schema in the schemas/event/secondary repository.
	 *
	 * @var string
	 */
	public const BASE_SCHEMA = '/analytics/product_metrics/web/base/1.0.0';

	/** @var EventSubmitter */
	private $eventSubmitter;

	/** @var Integration */
	private $integration;

	/** @var ContextController */
	private $contextController;

	/** @var CurationController */
	private $curationController;

	/** @var StreamConfigFactory */
	private $streamConfigFactory;

	/**
	 * @param EventSubmitter $eventSubmitter
	 * @param Integration $integration
	 * @param StreamConfigFactory $streamConfigFactory
	 * @param ?LoggerInterface $logger
	 * @param ?ContextController $contextController
	 * @param ?CurationController $curationController
	 */
	public function __construct(
		EventSubmitter $eventSubmitter,
		Integration $integration,
		StreamConfigFactory $streamConfigFactory,
		?LoggerInterface $logger = null,
		?ContextController $contextController = null,
		?CurationController $curationController = null
	) {
		$this->eventSubmitter = $eventSubmitter;
		$this->integration = $integration;
		$this->streamConfigFactory = $streamConfigFactory;
		$this->setLogger( $logger ?? new NullLogger() );
		$this->contextController = $contextController ?? new ContextController( $integration );
		$this->curationController = $curationController ?? new CurationController();
	}

	/**
	 * Submit an event to a stream.
	 *
	 * @param string $streamName
	 * @param array $eventData
	 *
	 * @stable
	 */
	public function submit( string $streamName, array $eventData ): void {
		$this->eventSubmitter->submit( $streamName, $eventData );
	}

	/**
	 * Submit an interaction event to a stream.
	 *
	 * @param string $streamName
	 * @param string $schemaId
	 * @param string $action
	 * @param array $interactionData
	 */
	public function submitInteraction(
		string $streamName,
		string $schemaId,
		string $action,
		array $interactionData
	): void {
		$event = $this->createEvent( $action, $schemaId );
		$formattedInteractionData = $this->getInteractionData( $action, $interactionData );
		$eventData = array_merge( $event, $formattedInteractionData );
		$this->eventSubmitter->submit( $streamName, $eventData );
	}

	/**
	 * Submit a click event to a stream.
	 *
	 * @param string $streamName
	 * @param array $interactionData
	 */
	public function submitClick(
		string $streamName,
		array $interactionData
	): void {
		$this->submitInteraction( $streamName, self::BASE_SCHEMA, 'click', $interactionData );
	}

	/**
	 * Get an ISO 8601 timestamp for the current time, e.g. 2022-05-03T14:00:41.000Z.
	 *
	 * Note well that the timestamp contains milliseconds for consistency with other Metrics
	 * Platform Client implementations.
	 *
	 * @return string
	 */
	private function getTimestamp(): string {
		return gmdate( 'Y-m-d\TH:i:s.v\Z' );
	}

	/**
	 * Constructs a "Metrics Platform Event" event given the event name and custom data. The event
	 * is submitted to all streams that are interested in the event.
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
	 *
	 * @unstable
	 * @deprecated
	 */
	public function dispatch( string $eventName, array $customData = [] ): void {
		$customData = $this->formatCustomData( $customData );
		$streamNames = $this->streamConfigFactory->getStreamNamesForEvent( $eventName );

		foreach ( $streamNames as $streamName ) {
			$event = $this->createEvent( $eventName );

			if ( $customData ) {
				$event['custom_data'] = $customData;
			}

			$streamConfig = $this->streamConfigFactory->getStreamConfig( $streamName );
			$event = $this->contextController->addRequestedValues( $event, $streamConfig );

			if ( $this->curationController->shouldProduceEvent( $event, $streamConfig ) ) {
				$this->submit( $streamName, $event );
			}
		}
	}

	/**
	 * @param string $eventName
	 * @param string|null $schemaId
	 */
	private function createEvent( string $eventName, string $schemaId = null ): array {
		$event = [
			'$schema' => $schemaId ?? self::MONO_SCHEMA,
			'dt' => $this->getTimestamp()
		];
		// Add the "name" key if monoschema is being used.
		if ( $event['$schema'] === self::MONO_SCHEMA ) {
			$event['name'] = $eventName;
		}
		return $event;
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
