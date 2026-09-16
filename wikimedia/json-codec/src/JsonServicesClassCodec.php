<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 * @ingroup Json
 */

namespace Wikimedia\JsonCodec;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;

/**
 * This is a simple class codec which proxies to methods on the object for
 * serialization and a static method on the class for deserialization,
 * supplying each with the services its signature requests.
 *
 * @implements JsonClassCodec<JsonCodecableTrait>
 */
class JsonServicesClassCodec implements JsonClassCodec {
	/**
	 * Create a new JsonServicesClassCodec that will pass the given
	 * $serializeServices whenever `toJsonArray` is called, and the
	 * given $deserializeServices whenever `newFromJsonArray` is
	 * called.
	 * @param list<mixed> $serializeServices Passed to ::toJsonArray()
	 * @param list<mixed> $deserializeServices Passed to ::newFromJsonArray()
	 */
	private function __construct(
		private readonly array $serializeServices,
		private readonly array $deserializeServices,
	) {
	}

	/**
	 * Returns a JSON array representing the contents of the given object, that
	 * can be deserialized with the corresponding newFromJsonArray() method,
	 * using a ::toJsonArray() method on the object itself.
	 *
	 * @param object $obj An object of the type handled by this JsonClassCodec
	 * @return array A Json representation of the object.
	 * @inheritDoc
	 * @see JsonCodecableTrait
	 */
	public function toJsonArray( $obj ): array {
		// Proxy to a method on the object itself.
		// @see JsonCodecableWithServicesTrait
		return $obj->toJsonArray( ...$this->serializeServices );
	}

	/**
	 * Creates a new instance of the given class and initializes it from the
	 * $json array, using a static method on $className.
	 *
	 * @inheritDoc
	 * @see JsonCodecableTrait
	 */
	public function newFromJsonArray( string $className, array $json ) {
		// Proxy to a static method on the class.
		// @see JsonCodecableWithServicesTrait
		return $className::newFromJsonArray( $json, ...$this->deserializeServices );
	}

	/**
	 * Return an optional type hint for the given array key in the result of
	 * ::toJsonArray() / input to ::newFromJsonArray.  If a class name is
	 * returned here and it matches the runtime type of the value of that
	 * array key, then type information will be omitted from the generated
	 * JSON which can save space.  The class name can be suffixed with `[]`
	 * to indicate an array or list containing objects of the given class
	 * name.
	 *
	 * @param class-string<T> $className
	 * @param string $keyName
	 * @return class-string|string|Hint|null A class string, Hint or null.
	 *   For backward compatibility, a class string suffixed with `[]` can
	 *   also be returned, but that is deprecated.
	 */
	public function jsonClassHintFor( string $className, string $keyName ) {
		// Proxy to a static method on the class.
		// @see JsonCodecableTrait
		return $className::jsonClassHintFor( $keyName );
	}

	/**
	 * Create a codec for $className, locating the services requested by the
	 * signatures of its ::toJsonArray() and ::newFromJsonArray() methods.
	 *
	 * @param class-string $className
	 * @param ContainerInterface $serviceContainer
	 * @return self
	 */
	public static function newForClass(
		string $className, ContainerInterface $serviceContainer
	): self {
		return new self(
			self::resolveServices(
				$className, 'toJsonArray', 0, $serviceContainer
			),
			self::resolveServices(
				$className, 'newFromJsonArray', 1, $serviceContainer
			)
		);
	}

	/**
	 * Fetch one service for each parameter of $method after the first $skip.
	 *
	 * @param class-string $className
	 * @param string $method Method name
	 * @param int $skip How many arguments precede the service list
	 * @param ContainerInterface $container The service container
	 * @return list<mixed> The services for $method in $className
	 */
	private static function resolveServices(
		string $className, string $method, int $skip,
		ContainerInterface $container
	): array {
		$services = [];
		foreach ( self::serviceParams( $className, $method, $skip ) as [ , $name, $optional ] ) {
			if ( $optional && !$container->has( $name ) ) {
				$services[] = null;
			} else {
				$services[] = $container->get( $name );
			}
		}
		return $services;
	}

	/**
	 * Determine the service container id for each parameter of $method
	 * after the first $skip, without actually resolving the services from
	 * a container.  Throws an InvalidArgumentException if any of those
	 * parameters is not properly annotated with a #[CodecService]
	 * attribute.
	 *
	 * @param class-string $className
	 * @param string $method Method name
	 * @param int $skip How many arguments precede the service list
	 * @return list<array{ReflectionParameter,string,bool}> A
	 *   [ $param, $name, $optional ] tuple
	 *   for each parameter of $method after the first $skip.
	 */
	public static function serviceParams(
		string $className, string $method, int $skip
	): array {
		$result = [];
		$params = ( new ReflectionMethod( $className, $method ) )
			->getParameters();
		foreach ( array_slice( $params, $skip ) as $param ) {
			$result[] = [ $param, ...self::serviceName( $className, $method, $param ) ];
		}
		return $result;
	}

	/**
	 * The #[CodecService] name and optional flag from $param if present,
	 * otherwise throw an InvalidArgumentException.
	 * @return array{string,bool}
	 */
	private static function serviceName(
		string $className, string $method, ReflectionParameter $param
	): array {
		$attrs = $param->getAttributes( CodecService::class );
		if ( count( $attrs ) === 1 ) {
			$att = $attrs[0]->newInstance();
			return [ $att->name, $att->optional ];
		}
		throw new InvalidArgumentException(
			"$className::$method(): parameter \$" . $param->getName() .
				" must name its service explicitly with exactly one " .
				"#[CodecService] annotation."
		);
	}
}
