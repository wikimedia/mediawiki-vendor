<?php

namespace Wikimedia\MetricsPlatform\StreamConfig;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use stdClass;

class ValidatingStreamConfigFactory extends StreamConfigFactory {

	/** @var stdClass */
	private $schema;

	/**
	 * @param array|false $rawStreamConfigs
	 * @param Validator $validator
	 */
	public function __construct(
		$rawStreamConfigs,
		private readonly Validator $validator
	) {
		parent::__construct( $rawStreamConfigs );

		$this->schema = (object)[ '$ref' => 'file://' . __DIR__ . '/data/metrics_platform_client.schema.json' ];
	}

	/**
	 * @inheritDoc
	 *
	 * @throws StreamConfigException If the given stream configuration does not validate against
	 *  the Metrics Platform Client configuration schema
	 */
	public function getStreamConfig( $streamName ): StreamConfig {
		$result = parent::getStreamConfig( $streamName );

		if ( $this->rawStreamConfigs === false ) {
			return $result;
		}

		$streamConfig = $this->rawStreamConfigs[$streamName];
		$metricsPlatformClientConfig = $streamConfig['producers']['metrics_platform_client'] ?? [];

		// If there is no Metrics Platform Client configuration to validate, then do not attempt to
		// validate it - at the very least, this avoids reading the schema from disk.
		if ( !$metricsPlatformClientConfig ) {
			return $result;
		}

		$this->validator->validate(
			$metricsPlatformClientConfig,
			$this->schema,

			// From https://github.com/justinrainbow/json-schema#configuration-options
			//
			// > Enable fuzzy type checking for associative arrays and objects
			Constraint::CHECK_MODE_TYPE_CAST
		);

		if ( !$this->validator->isValid() ) {
			$validationErrors = array_map(
				static function ( array $error ): string {
					return sprintf(
						'%s: %s',
						$error[ 'pointer' ],
						$error[ 'message' ]
					);
				},
				$this->validator->getErrors()
			);
			$message = implode( "\n", $validationErrors );

			throw new StreamConfigException( $message );
		}

		return $result;
	}
}
