<?php

namespace SmashPig\Tests\Helpers;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 *
 * @package SmashPig\Tests
 * @group Helpers
 */
class CurrencyRoundingHelperTest extends BaseSmashPigUnitTestCase {

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

	public function testRoundExponent3CurrencyAmount() {
		$iraqiDinarCurrencyCode = 'IQD';
		$amountToRound = (float)'74.6989';

		$rounded = CurrencyRoundingHelper::round( $amountToRound, $iraqiDinarCurrencyCode );

		$this->assertSame( '74.699', $rounded );
	}
}
