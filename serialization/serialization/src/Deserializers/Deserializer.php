<?php

namespace Deserializers;

use Deserializers\Exceptions\DeserializationException;

/**
 * @since 1.0
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Deserializer {

	/**
	 * Turns a serialized version of an object into the object.
	 * The input is the intermediate form obtained after doing the
	 * type independent deserialization work, such as for instance json_decode.
	 *
	 * Deserializers normally support only one or a few types
	 * of objects, since they do the type specific part of the wider deserialization
	 * process. An exception must be thrown when an object that is not supported by
	 * the deserializer is passed to it.
	 *
	 * @since 1.0
	 *
	 * @param mixed $serialization
	 *
	 * @return object
	 * @throws DeserializationException
	 */
	public function deserialize( $serialization );

}
