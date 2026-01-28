<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Test;

use SmashPig\PaymentProviders\PayPal\Audit\PayPalAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify PayPal audit file normalization functions
 *
 * @group PayPal
 * @group Audit
 */
class AuditTest extends BaseSmashPigUnitTestCase {

	/**
	 * @param string $file
	 * @return array
	 */
	protected function processFile( string $file ): array {
		return ( new PayPalAudit() )->parseFile( __DIR__ . '/../Data/' . $file );
	}
}
