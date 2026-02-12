<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Test;

use SmashPig\PaymentProviders\Trustly\Audit\TrustlyAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Trustly audit file normalization functions
 *
 * @group PayPal
 * @group Audit
 */
class AuditTestBase extends BaseSmashPigUnitTestCase {

	/**
	 * @param string $file
	 * @return array
	 */
	protected function processFile( string $file ): array {
		return ( new TrustlyAudit() )->parseFile( __DIR__ . '/../Data/' . $file );
	}
}
