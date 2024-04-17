<?php

namespace Wikimedia\MetricsPlatform;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfigException;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfigFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class MetricsClient implements LoggerAwareInterface {
	use LoggerAwareTrait;
	use InteractionDataTrait;

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

		try {
			$streamConfig = $this->streamConfigFactory->getStreamConfig( $streamName );

			$eventData = $this->contextController->addRequestedValues( $eventData, $streamConfig );
		} catch ( StreamConfigException $e ) {
			return;
		}

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
		return ConvertibleTimeStamp::now( TS_ISO_8601 );
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
}
