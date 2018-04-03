<?php

namespace Serializers;

use InvalidArgumentException;
use Serializers\Exceptions\UnsupportedObjectException;

/**
 * @since 1.0
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DispatchingSerializer implements DispatchableSerializer {

	/**
	 * @var DispatchableSerializer[]
	 */
	private $serializers;

	/**
	 * @param DispatchableSerializer[] $serializers
	 */
	public function __construct( array $serializers = [] ) {
		$this->assertAreSerializers( $serializers );
		$this->serializers = $serializers;
	}

	private function assertAreSerializers( array $serializers ) {
		foreach ( $serializers as $serializer ) {
			if ( !( $serializer instanceof DispatchableSerializer ) ) {
				throw new InvalidArgumentException(
					'Got an object that is not an instance of DispatchableSerializer'
				);
			}
		}
	}

	public function serialize( $object ) {
		foreach ( $this->serializers as $serializer ) {
			if ( $serializer->isSerializerFor( $object ) ) {
				return $serializer->serialize( $object );
			}
		}

		throw new UnsupportedObjectException( $object );
	}

	public function isSerializerFor( $object ) {
		foreach ( $this->serializers as $serializer ) {
			if ( $serializer->isSerializerFor( $object ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @since 3.0
	 *
	 * @param DispatchableSerializer $serializer
	 */
	public function addSerializer( DispatchableSerializer $serializer ) {
		$this->serializers[] = $serializer;
	}

}
