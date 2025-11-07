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

	/**
	 * The ID of the Metrics Platform base schema in the schemas/event/secondary repository.
	 *
	 * @var string
	 */
	public const BASE_SCHEMA = '/analytics/product_metrics/web/base/1.4.3';

	/** @var EventSubmitter */
	private $eventSubmitter;

	/** @var Integration */
	private $integration;

	/** @var ContextController */
	private $contextController;

	/** @var StreamConfigFactory */
	private $streamConfigFactory;

	/**
	 * @param EventSubmitter $eventSubmitter
	 * @param Integration $integration
	 * @param StreamConfigFactory $streamConfigFactory
	 * @param ?LoggerInterface $logger
	 * @param ?ContextController $contextController
	 */
	public function __construct(
		EventSubmitter $eventSubmitter,
		Integration $integration,
		StreamConfigFactory $streamConfigFactory,
		?LoggerInterface $logger = null,
		?ContextController $contextController = null
	) {
		$this->eventSubmitter = $eventSubmitter;
		$this->integration = $integration;
		$this->streamConfigFactory = $streamConfigFactory;
		$this->setLogger( $logger ?? new NullLogger() );
		$this->contextController = $contextController ?? new ContextController( $integration );
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
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/PHP_API
	 */
	public function submitInteraction(
		string $streamName,
		string $schemaId,
		string $action,
		array $interactionData = []
	): void {
		// See https://gitlab.wikimedia.org/repos/data-engineering/metrics-platform/-/blob/f7d52c6394a26f9de9cfe0fadbbc3c0dfe51b095/js/src/MetricsClient.js#L458
		$event = array_merge(
			[
				'action' => $action,
			],
			$interactionData,
			[
				'$schema' => $schemaId,
				'dt' => $this->getTimestamp()
			]
		);

		try {
			$streamConfig = $this->streamConfigFactory->getStreamConfig( $streamName );

			$event = $this->contextController->addRequestedValues( $event, $streamConfig );
		} catch ( StreamConfigException ) {
			return;
		}

		$this->eventSubmitter->submit( $streamName, $event );
	}

	/**
	 * Submit a click event to a stream.
	 *
	 * @param string $streamName
	 * @param array $interactionData
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/PHP_API
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
}
