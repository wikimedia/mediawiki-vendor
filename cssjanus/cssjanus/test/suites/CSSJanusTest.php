<?php

class CSSJanusTest extends PHPUnit_Framework_TestCase {

	public static function provideData() {
		$data = json_decode(file_get_contents(__DIR__ . '/../data.json'), /* $assoc = */ true);
		$cases = array();
		$defaultSettings = array(
			'swapLtrRtlInUrl' => false,
			'swapLeftRightInUrl' => false,
		);
		foreach ($data as $name => $test) {
			$settings = isset($test['settings']) ? $test['settings'] : array();
			$settings += $defaultSettings;
			foreach ($test['cases'] as $i => $case) {
				$input = $case[0];
				$noop = !isset($case[1]);
				$output = $noop ? $input : $case[1];

				$cases[] = array(
					$input,
					$settings,
					$output,
					$name,
				);

				if (!$noop) {
					// Round trip
					$cases[] = array(
						$output,
						$settings,
						$input,
						$name,
					);
				}
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider provideData
	 */
	public function testTransform($input, $settings, $output, $name) {
		$this->assertEquals(
			$output,
			CSSJanus::transform($input, $settings['swapLtrRtlInUrl'], $settings['swapLeftRightInUrl']),
			$name
		);
	}
}
