<?php
/**
 * @private
 */
class Less_Tree_Expression extends Less_Tree implements Less_Tree_HasValueProperty {

	/** @var Less_Tree[] */
	public $value = [];
	public $parens = false;

	public function __construct( $value, $parens = null ) {
		$this->value = $value;
		$this->parens = $parens;
	}

	public function accept( $visitor ) {
		$this->value = $visitor->visitArray( $this->value );
	}

	public function compile( $env ) {
		$doubleParen = false;

		if ( $this->parens && !$this->parensInOp ) {
			Less_Environment::$parensStack++;
		}

		$returnValue = null;
		if ( $this->value ) {

			$count = count( $this->value );

			if ( $count > 1 ) {

				$ret = [];
				foreach ( $this->value as $e ) {
					$ret[] = $e->compile( $env );
				}
				$returnValue = new self( $ret );

			} else {

				if ( ( $this->value[0] instanceof self ) && $this->value[0]->parens && !$this->value[0]->parensInOp ) {
					$doubleParen = true;
				}

				$returnValue = $this->value[0]->compile( $env );
			}

		} else {
			$returnValue = $this;
		}

		if ( $this->parens ) {
			if ( !$this->parensInOp ) {
				Less_Environment::$parensStack--;

			} elseif ( !$env->isMathOn() && !$doubleParen ) {
				$returnValue = new Less_Tree_Paren( $returnValue );

			}
		}
		return $returnValue;
	}

	/**
	 * @see Less_Tree::genCSS
	 */
	public function genCSS( $output ) {
		$val_len = count( $this->value );
		for ( $i = 0; $i < $val_len; $i++ ) {
			$this->value[$i]->genCSS( $output );
			if ( $i + 1 < $val_len ) {
				$output->add( ' ' );
			}
		}
	}

	public function throwAwayComments() {
		if ( is_array( $this->value ) ) {
			$new_value = [];
			foreach ( $this->value as $v ) {
				if ( $v instanceof Less_Tree_Comment ) {
					continue;
				}
				$new_value[] = $v;
			}
			$this->value = $new_value;
		}
	}

	public function markReferenced() {
		if ( is_array( $this->value ) ) {
			foreach ( $this->value as $v ) {
				if ( method_exists( $v, 'markReferenced' ) ) {
					$v->markReferenced();
				}
			}
		}
	}

	/**
	 * Should be used only in Less_Tree_Call::functionCaller()
	 * to retrieve expression without comments
	 * @internal
	 */
	public function mapToFunctionCallArgument() {
		if ( is_array( $this->value ) ) {
			$subNodes = [];
			foreach ( $this->value as $subNode ) {
				if ( !( $subNode instanceof Less_Tree_Comment ) ) {
					$subNodes[] = $subNode;
				}
			}
			return count( $subNodes ) === 1
				? $subNodes[0]
				: new Less_Tree_Expression( $subNodes );
		}
		return $this;
	}
}
