<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

use Psr\Container\ContainerInterface;

/**
 * The JsonCodecableWithServicesTrait aids in the implementation of codecs
 * which require access to services using the Dependency Injection pattern.
 *
 * The class using the trait must define ::toJsonArray() and
 * ::newFromJsonArray() methods, declaring one additional parameter
 * for each service it requires.  The services are located by
 * reflecting on those parameters, so each one must name its service
 * container entry with the #[CodecService] attribute.  The two
 * methods are reflected independently, so a class may require
 * different services for serialization and deserialization, or none
 * at all for one of them.
 *
 * For example:
 *
 *     class ServicesObject implements JsonCodecable {
 *         use JsonCodecableWithServicesTrait;
 *
 *         public function toJsonArray(): array {
 *             // Serialization needs no services here.
 *             return [ 'name' => $this->name ];
 *         }
 *
 *         public static function newFromJsonArray(
 *             array $json,
 *             #[CodecService( 'ServicesObjectFactory' )]
 *             ServicesObjectFactory $sof
 *         ): self {
 *             return $sof->lookup( $json['name'] );
 *         }
 *     }
 *
 * The class using the trait should also implement JsonCodecable
 * (https://wiki.php.net/rfc/traits-with-interfaces may allow the trait
 * to do this directly in a future PHP version).
 *
 * You should also add JsonCodecableWithServicesTestTrait to any
 * phpunit test covering the class using this trait.
 */
trait JsonCodecableWithServicesTrait {

	/**
	 * Implements JsonCodecable by providing an implementation of
	 * ::jsonClassCodec() which does not use the provided $serviceContainer
	 * nor does it maintain any state; it just calls the ::toJsonArray()
	 * and ::newFromJsonArray() methods of this instance.
	 * @param JsonCodecInterface $codec
	 * @param ContainerInterface $serviceContainer
	 * @return JsonClassCodec
	 */
	public static function jsonClassCodec(
		JsonCodecInterface $codec, ContainerInterface $serviceContainer
	): JsonClassCodec {
		return JsonServicesClassCodec::newForClass(
			static::class, $serviceContainer
		);
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
	 * Default implementation of ::jsonClassHintFor() provides no hints.
	 * Implementer can override.
	 *
	 * @param string $keyName
	 * @return class-string|string|Hint|null A class string, Hint, or null.
	 *   For backward compatibility, a class string suffixed with `[]` can
	 *   also be returned, but that is deprecated.
	 */
	public static function jsonClassHintFor( string $keyName ) {
		return null;
	}
}
