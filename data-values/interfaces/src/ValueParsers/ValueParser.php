<?php

declare( strict_types = 1 );

namespace ValueParsers;

/**
 * Interface for value parsers, typically (but not limited to) expecting a string and returning a
 * DataValue object.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface ValueParser {

	/**
	 * Identifier for the option that holds the code of the language in which the parser should
	 * operate.
	 * @since 0.1
	 */
	public const OPT_LANG = 'lang';

	/**
	 * @since 0.1
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 * @throws ParseException
	 */
	public function parse( $value );

}
