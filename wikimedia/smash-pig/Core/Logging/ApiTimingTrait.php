<?php

namespace SmashPig\Core\Logging;

use SmashPig\Core\Context;

/**
 * Provides standardised API call timing for payment processor integrations.
 *
 * Each method that uses timedCall() must be annotated with an #[ApiOperationAttribute] attribute
 * specifying its canonical operation type. This allows consistent naming across
 * different payment processors for timing logs and metrics.
 *
 * Usage example:
 *
 *     use SmashPig\Core\Logging\ApiOperationAttribute;
 *     use SmashPig\Core\Logging\ApiOperation;
 *
 *     class Api {
 *         use ApiTimingTrait;
 *
 *         #[ApiOperationAttribute( ApiOperation::AUTHORIZE )]
 *         public function createPayment( array $params ): array {
 *             return $this->timedCall( __FUNCTION__, function () use ( $params ) {
 *                 // Make API call here
 *                 return $this->client->post( '/payments', $params );
 *             } );
 *         }
 *     }
 *
 * This will emit a log line like:
 *     [processor|paymentMethod|authorize|request|time] 1.234567s
 *
 * @see ApiOperationAttribute The attribute class used to annotate methods
 * @see ApiOperation The enum of canonical operation types
 */
trait ApiTimingTrait {

	/**
	 * Executes a given callable while tracking the execution time for logging purposes.
	 *
	 * @param string $apiMethod The name of the API method being called. Must have an #[ApiOperationAttribute] attribute.
	 * @param callable $fn The callable function to be executed.
	 * @param array $context Additional context information to be passed for logging purposes.
	 * @param TaggedLogger|null $logger An optional logger instance for timing and error reporting.
	 */
	protected function timedCall(
		string $apiMethod,
		callable $fn,
		array $context = [],
		?TaggedLogger $logger = null
	) {
		$processorName = $this->getProcessorNameForTimings();
		$operation = $this->getOperationFromAttribute( $apiMethod );

		$tag = ApiTimings::buildTag(
			$processorName,
			$this->getPaymentMethodForTimings(),
			$operation->value
		);

		$start = microtime( true );
		try {
			return $fn();
		} finally {
			ApiTimings::log( $tag, microtime( true ) - $start, $context, $logger );
		}
	}

	/**
	 * Gets the ApiOperation from the #[ApiOperationAttribute] attribute on the given API method.
	 *
	 * @param string $methodName The name of the method to inspect
	 * @return ApiOperation The canonical operation for this method
	 * @throws \UnexpectedValueException|\ReflectionException If the method doesn't have an #[ApiOperationAttribute] attribute
	 */
	private function getOperationFromAttribute( string $methodName ): ApiOperation {
		$reflectionMethod = new \ReflectionMethod( $this, $methodName );
		$attributes = $reflectionMethod->getAttributes( ApiOperationAttribute::class );

		if ( empty( $attributes ) ) {
			$className = get_class( $this );
			throw new \UnexpectedValueException(
				"Method {$className}::{$methodName} is missing the #[ApiOperationAttribute] attribute"
			);
		}

		return $attributes[0]->newInstance()->operation;
	}

	protected function getProcessorNameForTimings(): string {
		$providerName = Context::get()->getProviderConfiguration()->getProviderName();
		return !empty( $providerName ) ? strtolower( $providerName ) : 'unknown';
	}

	protected function getPaymentMethodForTimings(): string {
		$paymentMethod = Context::get()->getProviderConfiguration()->getPaymentMethod();
		return !empty( $paymentMethod ) ? strtolower( $paymentMethod ) : 'unknown';
	}
}
