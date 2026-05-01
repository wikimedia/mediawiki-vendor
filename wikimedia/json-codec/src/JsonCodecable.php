<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

use Psr\Container\ContainerInterface;

/**
 * Classes implementing this interface support round-trip JSON
 * serialization/deserialization via a JsonClassCodec object
 * (which may maintain state and/or consult service objects).
 * It requires a single static method to be defined which
 * allows the creation of an appropriate JsonClassCodec
 * for this class.
 */
interface JsonCodecable {

	/**
	 * Create a JsonClassCodec which can serialize/deserialize instances of
	 * this class.
	 * @param JsonCodecInterface $codec A codec which can be used to handle
	 *  certain cases of implicit typing in the generated JSON; see
	 *  `JsonCodecInterface` for details.  It should not be necessary for
	 *  most class codecs to use this, as recursive
	 *  serialization/deserialization is handled by default.
	 * @param ContainerInterface $serviceContainer A service container
	 * @return JsonClassCodec A JsonClassCodec appropriate for objects of
	 *  this type.
	 */
	public static function jsonClassCodec(
		JsonCodecInterface $codec,
		ContainerInterface $serviceContainer
	): JsonClassCodec;
}
