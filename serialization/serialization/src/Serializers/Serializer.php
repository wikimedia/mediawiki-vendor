<?php

namespace Serializers;

use Serializers\Exceptions\SerializationException;

/**
 * @since 1.0
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Serializer {

	/**
	 * Returns the provided object as a representation suitable for serialization via
	 * for instance json_encode.
	 *
	 * Serializers normally support only one or a few types
	 * of objects, since they do the type specific part of the wider serialization
	 * process. An exception must be thrown when an object that is not supported by
	 * the serializer is passed to it.
	 *
	 * Serializers should provide a transformation of the whole object, without adding
	 * any additional data fetched via services.
	 *
	 * @since 1.0
	 *
	 * @param mixed $object
	 *
	 * @return array|int|string|bool|float A possibly nested structure consisting of only arrays and
	 *  scalar values.
	 * @throws SerializationException
	 */
	public function serialize( $object );

}
