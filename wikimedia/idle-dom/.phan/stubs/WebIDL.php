<?php

namespace Wikimedia\WebIDL;

/**
 * WebIDL (https://github.com/wikimedia/WebIDL)
 * Copyright (c) 2021, C. Scott Ananian. (MIT licensed)
 *
 * PHP library loosely inspired by
 * https://github.com/w3c/webidl2.js
 */
class WebIDL {
	/**
	 * Return an AST corresponding to the WebIDL input string.
	 * @param string $webidl The WebIDL input string to parse.
	 * @param array $options Optional parser options
	 * @return array The Abstract Syntax Tree
	 */
	public static function parse( string $webidl, array $options = [] ): array {
		return [];
	}
}
