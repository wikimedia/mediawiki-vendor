<?php
declare( strict_types = 1 );

namespace Shellbox\ShellParser;

/**
 * A wrapper for the shell syntax tree, providing a higher-level API.
 */
class SyntaxTree {
	/**
	 * @internal Use ShellParser::parse()
	 */
	public function __construct( private readonly Node $root ) {
	}

	/**
	 * Get the root node
	 *
	 * @return Node
	 */
	public function getRoot() {
		return $this->root;
	}

	/**
	 * Extract information about the syntax tree
	 *
	 * @return SyntaxInfo
	 */
	public function getInfo() {
		return new SyntaxInfo( $this->root );
	}
}
