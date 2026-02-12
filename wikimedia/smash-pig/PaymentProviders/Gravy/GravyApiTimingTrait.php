<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\ApiOperation;
use SmashPig\Core\Logging\ApiOperationAttribute;
use SmashPig\Core\Logging\ApiTimings;
use SmashPig\Core\Logging\TaggedLogger;

/**
 * Gravy-specific API timing trait that adds orchestrator|backend_processor layer.
 *
 * Emits logs in the format:
 *     [gravy|adyen|cc|authorize|request|time] 1.234567s
 *
 * Where "gravy" is the orchestrator and "adyen" is the backend processor extracted
 * from the response's payment_service.payment_service_definition_id field.
 *
 * For calls without a backend processor (reports, service definitions), emits:
 *     [gravy||cc|createSession|request|time] 1.234567s
 */
trait GravyApiTimingTrait {

	/**
	 * Executes a callable while tracking execution time, with Gravy orchestrator context.
	 *
	 * @param string $apiMethod The name of the API method being called.
	 * @param callable $fn The callable function to be executed.
	 * @param array $context Additional context for logging.
	 * @param TaggedLogger|null $logger Optional logger instance.
	 */
	protected function timedCall(
		string $apiMethod,
		callable $fn,
		array $context = [],
		?TaggedLogger $logger = null
	) {
		$operation = $this->getOperationFromAttribute( $apiMethod );

		$start = microtime( true );
		$result = null;
		try {
			$result = $fn();
			return $result;
		} finally {
			$backendProcessor = $this->extractBackendProcessorFromResponse( $result );
			$tag = $this->buildOrchestratorTag( $backendProcessor, $operation->value );
			ApiTimings::log( $tag, microtime( true ) - $start, $context, $logger );
		}
	}

	/**
	 * Builds tag with orchestrator|backend_processor format.
	 *
	 * Format is always: [gravy|backend|paymentMethod|operation|request|time]
	 * When the backend processor is unknown, the segment is left empty: [gravy||paymentMethod|operation|request|time]
	 *
	 * @param string|null $backendProcessor e.g. "adyen", "paypal", "braintree"
	 * @param string $operation The API operation name
	 * @return string e.g. [gravy|adyen|cc|authorize|request|time]
	 */
	private function buildOrchestratorTag( ?string $backendProcessor, string $operation ): string {
		$parts = [
			'gravy',
			!empty( $backendProcessor ) ? strtolower( $backendProcessor ) : '',
			$this->getPaymentMethodForTimings(),
			$operation,
			'request',
			'time'
		];

		return '[' . implode( '|', $parts ) . ']';
	}

	/**
	 * Extracts backend processor from Gravy response.
	 *
	 * @param mixed $response The API response
	 * @return string|null The backend processor name or null if not present
	 */
	private function extractBackendProcessorFromResponse( $response ): ?string {
		if ( !is_array( $response ) ) {
			return null;
		}

		$paymentServiceDefinitionId =
			$response['payment_service']['payment_service_definition_id'] ?? null;

		if ( empty( $paymentServiceDefinitionId ) ) {
			return null;
		}

		return GravyHelper::extractProcessorNameFromServiceDefinitionId(
			$paymentServiceDefinitionId
		);
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

	protected function getPaymentMethodForTimings(): string {
		$paymentMethod = Context::get()->getProviderConfiguration()->getPaymentMethod();
		return !empty( $paymentMethod ) ? strtolower( $paymentMethod ) : 'unknown';
	}
}
