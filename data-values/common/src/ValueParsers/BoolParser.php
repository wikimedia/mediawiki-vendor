<?php

declare( strict_types = 1 );

namespace ValueParsers;

use DataValues\BooleanValue;

/**
 * ValueParser that parses the string representation of a boolean.
 *
 * @since 0.1
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class BoolParser extends StringValueParser {

	private const FORMAT_NAME = 'bool';

	/**
	 * @var Mapping from possible string values to their
	 *      boolean equivalents
	 */
	private static $values = [
		'yes' => true,
		'on' => true,
		'1' => true,
		'true' => true,
		'no' => false,
		'off' => false,
		'0' => false,
		'false' => false,
	];

	/**
	 * @see StringValueParser::stringParse
	 *
	 * @param string $value
	 *
	 * @return BooleanValue
	 * @throws ParseException
	 */
	protected function stringParse( $value ) {
		$rawValue = $value;

		$value = strtolower( $value );

		if ( array_key_exists( $value, self::$values ) ) {
			return new BooleanValue( self::$values[$value] );
		}

		throw new ParseException( 'Not a boolean', $rawValue, self::FORMAT_NAME );
	}

}
