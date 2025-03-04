<?php

namespace SmashPig\Tests;

use SmashPig\Core\Mapper\Mapper;
use SmashPig\Core\SmashPigException;

/**
 * @group Mapper
 */
class MapperTest extends BaseSmashPigUnitTestCase {

	public function testThrowsSmashPigExceptionIfCannotLoadMapFile() {
		$this->expectException( SmashPigException::class );

		$testMapFilePath = 'Tests/data/test_map_doesnt_exist.yaml';
		$output = Mapper::map( [], $testMapFilePath );
	}

	public function testThrowsSmashPigExceptionIfMapContainsInvalidYaml() {
		$this->expectException( SmashPigException::class );

		$testMapFilePath = 'Tests/data/test_map_not_valid_yaml.yaml';
		$output = Mapper::map( [], $testMapFilePath );
	}

	public function testMapReplacesString() {
		$testMapFilePath = 'Tests/data/test_map_string.yaml';
		$testMapVars['test_string_value'] = 'abc';

		$output = Mapper::map(
			$testMapVars,
			$testMapFilePath
		);

		$this->assertEquals( [ 'test-string' => 'abc' ], $output );
	}

	public function testMapReplacesArrayValue() {
		$testMapFilePath = 'Tests/data/test_map_array.yaml';
		$testMapVars['test_array_value'] = 'abc';

		$output = Mapper::map( $testMapVars, $testMapFilePath );

		$expected = [
			'test-array' => [
				'test-array-value' => 'abc',
			],
		];

		$this->assertEquals( $expected, $output );
	}

	public function testMapReplacesMultiDimensionalArrayValues() {
		$testMapFilePath = 'Tests/data/test_map_multi_array.yaml';

		$testMapVars = [
			'nested-array-value-one' => 'abc',
			'nested-array-value-two' => 'def',
			'nested-array-value-three' => 'ghi',
			'nested-array-value-four' => 'jkl',
		];

		$output = Mapper::map( $testMapVars, $testMapFilePath );

		$expected = [
			'test-nested-array' =>
				[
					'test-nested-array-one' =>
						[
							'test-nested-child-two' =>
								[
									'test-nested-child-three' =>
										[
											'nested-array-value-one' => 'abc',
											'nested-array-value-two' => 'def',
										],
								],
						],
					'test-nested-array-two' =>
						[
							'test-nested-child-two' =>
								[
									'test-nested-child-three' =>
										[
											'nested-array-value-three' => 'ghi',
											'nested-array-value-four' => 'jkl',
										],
								],
						],
				],
		];

		$this->assertEquals( $expected, $output );
	}

	public function testMapPrunesEmptyValues() {
		$testMapFilePath = 'Tests/data/test_map_multi_array.yaml';

		$testMapVars = [
			'nested-array-value-three' => 'ghi',
			'nested-array-value-four' => 'jkl',
		];

		$output = Mapper::map( $testMapVars, $testMapFilePath, [], null, true );

		$expected = [
			'test-nested-array' =>
				[
					'test-nested-array-two' =>
						[
							'test-nested-child-two' =>
								[
									'test-nested-child-three' =>
										[
											'nested-array-value-three' => 'ghi',
											'nested-array-value-four' => 'jkl',
										],
								],
						],
				],
		];

		$this->assertEquals( $expected, $output );
	}

	public function testMapReplacesCompoundValue() {
		$testMapFilePath = 'Tests/data/test_map_compound_value.yaml';
		$testMapVars['test_prefix_value'] = 'Mr';
		$testMapVars['test_first_name_value'] = 'Jimmy';
		$testMapVars['test_second_name_value'] = 'Wales';

		$testOutput = Mapper::map(
			$testMapVars,
			$testMapFilePath
		);

		$this->assertEquals( [ 'test-name' => 'Mr Jimmy Wales' ], $testOutput );
	}

	public function testMapClearsUnsetVariablePlaceholders() {
		$testMapFilePath = 'Tests/data/test_map_unset.yaml';
		$emptyTestMapVars = [];

		$output = Mapper::map( $emptyTestMapVars, $testMapFilePath );

		$expected = [
			'test-array' => [
				'test-array-value' => null,
			],
			'test-string' => null,
		];

		$this->assertEquals( $expected, $output );
	}

	public function testMapReplacesDuplicateValues() {
		$testMapFilePath = 'Tests/data/test_map_duplicates.yaml';

		// this should be injected twice.
		$testMapVars['test_array_value'] = 'abc';
		$output = Mapper::map( $testMapVars, $testMapFilePath );

		$expected = [
			'test-array' => [
				'test-array-value-one' => 'abc',
				'test-array-value-two' => 'abc',
			],
		];

		$this->assertEquals( $expected, $output );
	}

	public function testMapToOutputFormatJson() {
		$testMapFilePath = 'Tests/data/test_map_array.yaml';
		$testMapVars['test_array_value'] = 'abc';

		$output = Mapper::map(
			$testMapVars,
			$testMapFilePath,
			[],
			Mapper::FORMAT_JSON
		);

		$expected = '{"test-array":{"test-array-value":"abc"}}';

		$this->assertEquals( $expected, $output );
	}

	public function testMapperTransformsUsingClosure() {
		$testMapFilePath = 'Tests/data/test_map_string.yaml';
		$testMapVars['test_string_value'] = 'abc';

		$uppercaseTransformer = function ( $original, &$transformed ) {
			foreach ( $original as $key => $value ) {
				$transformed[$key] = strtoupper( $value );
			}
		};

		$output = Mapper::map(
			$testMapVars,
			$testMapFilePath,
			[ $uppercaseTransformer ]
		);

		$this->assertEquals( [ 'test-string' => 'ABC' ], $output );
	}

	public function testMapperTransformsUsingMultipleClosures() {
		$testMapFilePath = 'Tests/data/test_map_string.yaml';
		$testMapVars['test_string_value'] = 'abc';

		$uppercaseTransformer = function ( $original, &$transformed ) {
			foreach ( $original as $key => $value ) {
				$transformed[$key] = strtoupper( $transformed[$key] );
			}
		};

		$underscoreSeparatorTransformer = function ( $original, &$transformed ) {
			foreach ( $original as $key => $value ) {
				// using $transformed[$key] to allow for transformation "layering".
				$transformed[$key] = implode( "_", str_split( $transformed[$key] ) );
			}
		};

		$output = Mapper::map(
			$testMapVars,
			$testMapFilePath,
			[
				$uppercaseTransformer,
				$underscoreSeparatorTransformer,
			]
		);

		$this->assertEquals( [ 'test-string' => 'A_B_C' ], $output );
	}

	public function testMapperTransformerWithTransformerClass() {
		$testMapFilePath = 'Tests/data/test_map_money_transformer.yaml';
		$testMapVars = [
			'amount' => 10, // $10 here, but we want cents!
			'currency' => 'usd',
		];

		$output = Mapper::map(
			$testMapVars,
			$testMapFilePath,
			[ new \SmashPig\Core\Mapper\Transformers\AmountToCents() ]
		);

		$expected = [
			'amount' => 1000 // gimme those cents!!!
		];

		$this->assertEquals( $expected, $output );
	}

}
