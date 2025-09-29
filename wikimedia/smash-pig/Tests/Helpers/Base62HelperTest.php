<?php

namespace SmashPig\Tests\Helpers;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 *
 * @package SmashPig\Tests
 * @group Helpers
 */
class Base62HelperTest extends BaseSmashPigUnitTestCase {

	/**
	 * @dataProvider base62examples
	 * @return void
	 */
	public function testToUUid( string $decoded, string $encoded ) {
		$this->assertEquals( $decoded, Base62Helper::toUuid( $encoded ) );
	}

	public function base62examples(): array {
		return [
			[ '3f9c958c-ee57-4121-a79e-408946b27077', '1w24hGOdCSFLtsgBQr2jKh' ],
			[ '5e29d8d8-fbc9-4ff4-a01e-fd9bf7cbaff7', '2rgOyjduuteu6boloubm1X' ],
			[ '5b8cb37f-3388-4459-bae6-dd36bc41888c', '2mkbLUYjl8AzuDeOphQlQ4' ],
			[ '47248065-a201-4e29-a8d9-7806e8cc20cf', '2AF8Sax6b6qfI67qu0bTSx' ],
			[ '50864fa5-36cb-499b-a740-ef2bc6cd086e', '2RwlWSm1hlw1KDa0o95rpO' ],
			[ '5bd9a595-e898-4ce7-8805-67ee53922027', '2nJlWvNGwWcNOJ2pTZIdmh' ],

			// With leading zero(s) in Base62 (should still decode correctly).
			[ '003c2588-e931-4df8-bb84-21889d71485e', '01RUCLM3L6AGhD98pXAJby' ],
			[ '0070cb22-4f1f-4f78-be61-16bdcfb0f5d5', '01pXtmbkq0PB2bn24G2NbJ' ],
			[ '00f6688a-7d3e-46bb-8769-ae5ed4739aaa', '011obkkfOWGCIuWeu9xPtOs' ],
			[ '00001f8b-8451-4191-a0c4-d3567e3d05d4', '023USwAsLMPy3bndZ4ksO' ],
			[ '002baf10-3e38-4cf6-9cef-85b79db8036b', '01Jxjy2shysxrFKh3cicQd' ],
			[ '00b0132a-9ea3-40ea-9775-d03eefe755b5', '011ISuzj8i6081FKFY7fREz' ],
		];
	}
}
