<?php

namespace SmashPig\Core\Helpers;

/**
 * Not RFC 4122 compliant UUIDs, but just as random
 */
class UniqueId {
	public static function generate(): string {
		return bin2hex( random_bytes( 16 ) );
	}
}
