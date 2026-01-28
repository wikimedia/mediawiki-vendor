<?php

namespace SmashPig\Core\Logging;

use Attribute;

/**
 * Attribute to mark API methods with their canonical operation type.
 *
 * Usage:
 *     #[ApiOperationAttribute(ApiOperation::AUTHORIZE)]
 *     public function createPayment($params) { ... }
 */
#[Attribute( Attribute::TARGET_METHOD )]
class ApiOperationAttribute {
	public function __construct(
		public ApiOperation $operation
	) {
	}
}
