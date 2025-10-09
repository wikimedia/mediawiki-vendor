<?php

namespace SmashPig\Tests\Helpers;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 *
 * @package SmashPig\Tests
 * @group Helpers
 */
class Base62HelperTest extends BaseSmashPigUnitTestCase {

	public function testRoundFractionalCurrencyAmount() {
		$currencyCode = 'USD';
		$amountToRound = (float)'47.23332';

		$rounded = CurrencyRoundingHelper::round( $amountToRound, $currencyCode );

		$this->assertSame( '47.23', $rounded );
	}

	public function testRoundNonFractionalCurrencyAmount() {
		$currencyCode = 'JPY';
		$amountToRound = (float)'1000.05';

		$rounded = CurrencyRoundingHelper::round( $amountToRound, $currencyCode );

		$this->assertSame( '1000', $rounded );
	}

	public function testToUUid() {
		$this->assertEquals( '3f9c958c-ee57-4121-a79e-408946b27077', Base62Helper::toUuid( '1w24hGOdCSFLtsgBQr2jKh' ) );
	}
}
