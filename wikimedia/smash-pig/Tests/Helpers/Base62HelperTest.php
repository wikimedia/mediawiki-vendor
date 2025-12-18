<?php

namespace SmashPig\Tests\Helpers;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @package SmashPig\Tests
 * @group Helpers
 */
class Base62HelperTest extends BaseSmashPigUnitTestCase {

	/**
	 * @dataProvider base62examples
	 */
	public function testToUUid( string $decoded, string $encoded ) {
		$this->assertEquals( $decoded, Base62Helper::toUuid( $encoded ) );
	}

	/**
	 * UUID -> Base62 -> UUID should be identity (using canonical minimal Base62 from fromUuid()).
	 *
	 * @dataProvider uuidExamples
	 */
	public function testFromUuidRoundTrip( string $uuid ) {
		$encoded = Base62Helper::fromUuid( $uuid );
		$decoded = Base62Helper::toUuid( $encoded );
		$this->assertEquals( strtolower( $uuid ), $decoded );
	}

	/**
	 * Encoded fixture -> UUID -> canonical Base62 is stable (even if the fixture had leading zeros / repairable forms).
	 *
	 * @dataProvider base62examples
	 */
	public function testCanonicalizationIsStable( string $decoded, string $encoded ) {
		$uuid = Base62Helper::toUuid( $encoded );
		$canonical1 = Base62Helper::fromUuid( $uuid );
		$canonical2 = Base62Helper::fromUuid( Base62Helper::toUuid( $canonical1 ) );
		$this->assertSame( $canonical1, $canonical2 );
	}

	public function uuidExamples(): array {
		return [
			[ '3f9c958c-ee57-4121-a79e-408946b27077' ],
			[ '5e29d8d8-fbc9-4ff4-a01e-fd9bf7cbaff7' ],
			[ '5b8cb37f-3388-4459-bae6-dd36bc41888c' ],
			[ '47248065-a201-4e29-a8d9-7806e8cc20cf' ],
			[ '50864fa5-36cb-499b-a740-ef2bc6cd086e' ],
			[ '5bd9a595-e898-4ce7-8805-67ee53922027' ],
			[ '003c2588-e931-4df8-bb84-21889d71485e' ],
			[ '0070cb22-4f1f-4f78-be61-16bdcfb0f5d5' ],
			[ '00f6688a-7d3e-46bb-8769-ae5ed4739aaa' ],
			[ '00001f8b-8451-4191-a0c4-d3567e3d05d4' ],
			[ '002baf10-3e38-4cf6-9cef-85b79db8036b' ],
			[ '00b0132a-9ea3-40ea-9775-d03eefe755b5' ],
		];
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
