<?php

namespace SmashPig\Core\Mapper;

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Transform YAML map files containing %placeholders% to output.
 *
 * Mapper Basic:
 *
 * Usage:
 * map-file.yaml:
 *   sample-array:
 *       some-value: %var1%
 *
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile);
 *
 * output :
 * [
 *  'some-data' => [
 *     'some-value' => 'abc',
 *   ],
 * ]
 *
 * Mapper Basic to JSON:
 *
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile, null,
 * Mapper::FORMAT_JSON);
 *
 * output:
 * {
 *  "some-data": {
 *    "some-value": "abc"
 *   }
 * }
 *
 * Mapper with Transformers:
 *
 * Transformers allow changes to %placeholder% values and formats
 * during the mapping process. Transformers can be passed as a Closure with two
 * arguments ($original,&$transformed) or they can be classes that extend
 * Transformer.
 *
 * @see \SmashPig\Core\Mapper\Transformers\AbstractTransformer
 * @see \SmashPig\Core\Mapper\Transformers\Transformer
 *
 * Usage:
 *  $uppercaseTransformer = function ( $original, &$transformed ) {
 *    foreach ( $original as $key => $value ) {
 *      $transformed[$key] = strtoupper( $transformed[$key] );
 *    }
 *  };
 *
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile,
 *   [$uppercaseTransformer]);
 *
 * output :
 * [
 *  'some-data' => [
 *     'some-value' => 'ABC',
 *   ],
 * ]
 *
 * @package SmashPig\Core
 *
 */
class Mapper {

	const FORMAT_ARRAY = 'array';

	const FORMAT_JSON = 'json';

	/**
	 * Map YAML map files containing %placeholders% to output.
	 *
	 * @param array $input key=>value vars to overwrite map file %placeholders%
	 * @param string $mapFilePath map file path
	 * @param array $transformers
	 * @param string|null $outputFormat
	 * @param bool $pruneEmpty if true, remove subtrees with no values provided
	 *
	 * @return mixed
	 * @throws SmashPigException
	 */
	public static function map(
		array $input,
		string $mapFilePath,
		array $transformers = [],
		string $outputFormat = null,
		bool $pruneEmpty = false
	) {
		$mapper = new static;
		$yaml = $mapper->loadMapFile( $mapFilePath );
		$map = $mapper->convertYamlMapToArray( $yaml );

		if ( count( $transformers ) > 0 ) {
			$mapper->setupInputTransformers( $transformers );
			$input = $mapper->applyInputTransformers( $input, $transformers );
		}

		$output = $mapper->translatePlaceholdersToInput( $map, $input );
		if ( $pruneEmpty ) {
			$output = self::pruneEmptyValues( $output );
		}
		if ( isset( $outputFormat ) && $outputFormat != static::FORMAT_ARRAY ) {
			$output = $mapper->formatOutput( $output, $outputFormat );
		}

		return $output;
	}

	/**
	 * Load YAML map file
	 *
	 * @param string $mapFilePath
	 *
	 * @return bool|string
	 * @throws \SmashPig\Core\SmashPigException
	 */
	protected function loadMapFile( string $mapFilePath ) {
		$fullMapFilePath = __DIR__ . "/../../" . $mapFilePath;
		if ( !is_file( $fullMapFilePath ) ) {
			Logger::error( "File $fullMapFilePath does not exist." );
			throw new SmashPigException( "File $fullMapFilePath does not exist." );
		}

		if ( !is_readable( $fullMapFilePath ) ) {
			Logger::error( "File $fullMapFilePath cannot be read." );
			throw new SmashPigException( "File $fullMapFilePath cannot be read." );
		}

		return file_get_contents( $fullMapFilePath );
	}

	/**
	 * Convert YAML string to array
	 *
	 * @param string $yaml
	 *
	 * @return array
	 * @throws \SmashPig\Core\SmashPigException
	 */
	protected function convertYamlMapToArray( string $yaml ): array {
		try {
			return Yaml::parse( $yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE );
		} catch ( ParseException $exception ) {
			Logger::error( 'Unable to YAML parse map file :' . $exception->getMessage() );
			throw new SmashPigException( 'Unable to YAML parse map file :' . $exception->getMessage() );
		}
	}

	/**
	 * Convert transformer paths to classes if necessary
	 *
	 * @param array $transformers
	 */
	protected function setupInputTransformers( array &$transformers ) {
		foreach ( $transformers as $i => $transformer ) {
			if ( is_callable( $transformer ) ) {
				// All good, we have a Transformer class or callable Closure.
				continue;
			} elseif ( is_string( $transformer ) && class_exists( $transformer ) ) {
				$transformers[$i] = new $transformer();
			} else {
				Logger::error( "Transformer supplied not callable: " . $transformer );
				throw new \InvalidArgumentException(
					"Transformers should be callable or an instance of Transformer: " . $transformer
				);
			}
		}
	}

	/**
	 * Apply any input transformations.
	 *
	 * Note: $transformed is passed between all Transformers to allow
	 * "layering" of Transformer behaviour. Due to this, within the scope
	 * of your transformer method (or Closure transformer) always refer to
	 * $transformed['field'] for the latest version of that value and only
	 * use $original['field'] when you want to know the original value prior to
	 * any Transformations being applied.
	 *
	 * @param array $input
	 * @param array $transformers
	 *
	 * @return array
	 */
	protected function applyInputTransformers( array $input, array $transformers ): array {
		$transformed = $original = $input;
		foreach ( $transformers as $transformer ) {
			// $transformed passed by reference
			$transformer( $original, $transformed );
		}
		return $transformed;
	}

	/**
	 * Translate map file %placeholders% to input values and clear out any
	 * unset/unused %placeholders% strings.
	 *
	 * It's possible to use multiple %placeholders% within a value (compound value)
	 * e.g. 'Miss %first_name% %second_name%' will generate the expected output
	 * if both keys exists within $input.
	 *
	 * @param array $map
	 * @param array $input
	 *
	 * @return array
	 */
	protected function translatePlaceholdersToInput( array $map, array $input ): array {
		array_walk_recursive( $map, function ( &$value ) use ( $input ) {
			$replacements = [];
			// this is ugly php but it works, preg_match_all return values are horrible.
			if ( preg_match_all( "/%([^%]+)%/", $value, $matches ) >= 1 ) {
				foreach ( $matches[1] as $i => $match ) {
					$replacement = array_key_exists( $match, $input ) ? $input[$match] : null;
					$replacements[$matches[0][$i]] = $replacement;
				}
				$value = strtr( $value, $replacements );
			}
		} );

		return $map;
	}

	/**
	 * Trim empty values or subtrees of empty values out of an array
	 *
	 * @param array $input the array to be pruned
	 * @return array|null the input, with all empty keys removed.
	 */
	protected static function pruneEmptyValues( array $input ) {
		$output = [];
		foreach ( $input as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::pruneEmptyValues( $value );
			}
			if ( $value !== '' && $value !== null ) {
				$output[$key] = $value;
			}
		}
		if ( count( $output ) === 0 ) {
			return null;
		}
		return $output;
	}

	/**
	 * Transform output to desired format.
	 *
	 * @param array $output
	 * @param string $format
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function formatOutput( array $output, string $format ): string {
		switch ( $format ) {
			case static::FORMAT_JSON:
				return json_encode( $output );
			default:
				Logger::error( "Invalid Mapper output format supplied: " . $format );
				throw new \InvalidArgumentException( "Invalid Mapper output format supplied: " . $format );
		}
	}

}
