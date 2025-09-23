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

	public function testGetInMajorUnitsNonFractionalCurrency() {
		$currencyCode = 'JPY';
		$amount = (float)'1200';

		$major = CurrencyRoundingHelper::getAmountInMajorUnits( $amount, $currencyCode );

		$this->assertSame( '1200', $major );
	}

	public function testGetInMajorUnitsNonFractionalCurrencyLowAmount() {
		$currencyCode = 'JPY';
		$amount = (float)'12';

		$major = CurrencyRoundingHelper::getAmountInMajorUnits( $amount, $currencyCode );

		$this->assertSame( '12', $major );
	}

	public function testGetInMajorUnits() {
		$currencyCode = 'USD';
		$amount = (float)'3500';

		$major = CurrencyRoundingHelper::getAmountInMajorUnits( $amount, $currencyCode );

		$this->assertSame( '35.00', $major );
	}

	public function testGetInMajorUnitsTwoDecimal() {
		$currencyCode = 'USD';
		$amount = (float)'1234';

		$major = CurrencyRoundingHelper::getAmountInMajorUnits( $amount, $currencyCode );

		$this->assertSame( '12.34', $major );
	}

	public function testGetInMajorUnitsTwoDecimalLowAmount() {
		$currencyCode = 'USD';
		$amount = (float)'11';

		$major = CurrencyRoundingHelper::getAmountInMajorUnits( $amount, $currencyCode );

		$this->assertSame( '0.11', $major );
	}

	public function testGetInMajorUnitsThreeDecimal() {
		$currencyCode = 'BHD';
		$amount = (float)'123456';

		$major = CurrencyRoundingHelper::getAmountInMajorUnits( $amount, $currencyCode );

		$this->assertSame( '123.456', $major );
	}

	public function testGetInMajorUnitsThreeDecimalLowAmount() {
		$currencyCode = 'BHD';
		$amount = (float)'123';

		$major = CurrencyRoundingHelper::getAmountInMajorUnits( $amount, $currencyCode );

		$this->assertSame( '0.123', $major );
	}
}
