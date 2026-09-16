<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * A PHPUnit test trait which verifies that a class using
 * JsonCodecableWithServicesTrait declares ::toJsonArray() and
 * ::newFromJsonArray() with correct signatures.
 *
 * To use, `use` this trait in a PHPUnit TestCase and override
 * ::getCodecableClass() to name the class under test:
 *
 *     class MyCodecableClassTest extends TestCase {
 *         use JsonCodecableWithServicesTestTrait;
 *         protected static function getCodecableClass(): string {
 *             return MyCodecableClass::class;
 *         }
 *     }
 *
 * This verifies that MyCodecableClass declares ::toJsonArray() and
 * ::newFromJsonArray() and that every service parameter is properly
 * annotated with a #[CodecService] attribute, but not that the named
 * services actually exist nor that their types match the parameter's
 * type hint since there's no general way to look up the PHP type of an
 * arbitrary service name.
 *
 * If you are willing to run this as an integration test rather than a
 * unit test, you can also override ::getServiceContainer() to
 * return a (live or mocked) service container, in which case this
 * trait will also verify that every named service can be resolved from
 * that container and that the resulting object matches the type hint
 * of the corresponding parameter:
 *
 *     class MyCodecableClassTest extends TestCase {
 *         use JsonCodecableWithServicesTestTrait;
 *         protected static function getCodecableClass(): string {
 *             return MyCodecableClass::class;
 *         }
 *         protected function getServiceContainer(): ?ContainerInterface {
 *             return MyServiceContainer::getInstance();
 *         }
 *     }
 */
trait JsonCodecableWithServicesTestTrait {

	/**
	 * The class using JsonCodecableWithServicesTrait to verify.
	 * @return class-string
	 */
	abstract protected static function getCodecableClass(): string;

	/**
	 * An optional service container to use to verify that the services
	 * named by #[CodecService] attributes actually resolve to objects
	 * matching the declared parameter types.  Return null (the default)
	 * to skip this additional check and run as a unit test which only
	 * verifies the method signatures and attributes.
	 * @return ContainerInterface|null
	 */
	protected function getServiceContainer(): ?ContainerInterface {
		return null;
	}

	/**
	 * Verify that ::getCodecableClass() declares ::toJsonArray() and
	 * ::newFromJsonArray() with the signature required by
	 * JsonCodecableWithServicesTrait.
	 * @covers \Wikimedia\JsonCodec\JsonServicesClassCodec::serviceParams
	 */
	public function testJsonCodecableWithServicesSignature(): void {
		$class = static::getCodecableClass();
		Assert::assertTrue(
			is_a( $class, JsonCodecable::class, true ),
			"$class must implement JsonCodecable"
		);

		$toJsonArray = new ReflectionMethod( $class, 'toJsonArray' );
		Assert::assertTrue( $toJsonArray->isPublic(), "$class::toJsonArray() must be public" );
		Assert::assertFalse( $toJsonArray->isStatic(), "$class::toJsonArray() must not be static" );

		$newFromJsonArray = new ReflectionMethod( $class, 'newFromJsonArray' );
		Assert::assertTrue(
			$newFromJsonArray->isPublic(), "$class::newFromJsonArray() must be public"
		);
		Assert::assertTrue(
			$newFromJsonArray->isStatic(), "$class::newFromJsonArray() must be static"
		);
		$jsonParam = $newFromJsonArray->getParameters()[0] ?? null;
		Assert::assertNotNull(
			$jsonParam, "$class::newFromJsonArray() must accept a \$json parameter"
		);
		Assert::assertSame( 'json', $jsonParam->getName() );
		$jsonType = $jsonParam->getType();
		if ( !( $jsonType instanceof ReflectionNamedType ) ) {
			Assert::fail( "$class::newFromJsonArray()'s \$json parameter must have a named type" );
		}
		Assert::assertSame( 'array', $jsonType->getName() );

		// Reflecting on the parameters after the required prefix throws
		// unless every one of them is properly annotated with exactly one
		// #[CodecService] attribute.
		$serializeParams = JsonServicesClassCodec::serviceParams( $class, 'toJsonArray', 0 );
		$deserializeParams = JsonServicesClassCodec::serviceParams( $class, 'newFromJsonArray', 1 );

		$container = $this->getServiceContainer();
		if ( $container === null ) {
			// We can't check the types and names of the services.
			return;
		}
		foreach ( [ ...$serializeParams, ...$deserializeParams ] as [ $param, $name, $optional ] ) {
			if ( $optional ) {
				Assert::assertTrue(
					$param->allowsNull(),
					"Optional service '$name' must allow null values"
				);
				if ( !$container->has( $name ) ) {
					// An optional parameter which isn't present isn't an error,
					// but it does mean we can't fully validate the type.
					continue;
				}
			}
			$service = $container->get( $name );
			Assert::assertNotNull( $service, "Service '$name' must resolve to a non-null value" );
			$type = $param->getType();
			// In the future we could try harder to check union or intersection
			// types, but this code will handle the common single-type case.
			if ( $type instanceof ReflectionNamedType && !$type->isBuiltin() ) {
				Assert::assertInstanceOf(
					$type->getName(), $service,
					"Service '$name' must be an instance of " . $type->getName()
				);
			}
		}
	}
}
