<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html;

use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\SelectiveUpdateData;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * Wrap some stages into a pipeline.
 */

class ParserPipeline {
	private bool $alwaysToplevel;
	private bool $atTopLevel;
	private int $id;
	private string $outputType;
	private string $pipelineType;
	private array $stages;
	private Env $env;
	private string $cacheKey;
	private Frame $frame;

	public function __construct(
		bool $alwaysToplevel, string $type, string $outType, string $cacheKey, array $stages, Env $env
	) {
		$this->id = -1;
		$this->alwaysToplevel = $alwaysToplevel;
		$this->cacheKey = $cacheKey;
		$this->pipelineType = $type;
		$this->outputType = $outType;
		$this->stages = $stages;
		$this->env = $env;
	}

	public function getCacheKey(): string {
		return $this->cacheKey;
	}

	/**
	 * Applies the function across all stages and transformers registered at
	 * each stage.
	 *
	 * @param string $fn
	 * @param mixed ...$args
	 */
	private function applyToStage( string $fn, ...$args ): void {
		// Apply to each stage
		foreach ( $this->stages as $stage ) {
			$stage->$fn( ...$args );
		}
	}

	/**
	 * This is useful for debugging.
	 *
	 * @param int $id
	 */
	public function setPipelineId( int $id ): void {
		$this->id = $id;
		$this->applyToStage( 'setPipelineId', $id );
	}

	/**
	 * Reset any local state in the pipeline stage
	 * @param array $opts
	 */
	public function resetState( array $opts = [] ): void {
		$this->applyToStage( 'resetState', $opts );
	}

	/**
	 * Set source offsets for the source that this pipeline will process.
	 *
	 * This lets us use different pipelines to parse fragments of the same page
	 * Ex: extension content (found on the same page) is parsed with a different
	 * pipeline than the top-level page.
	 *
	 * Because of this, the source offsets are not [0, page.length) always
	 * and needs to be explicitly initialized
	 *
	 * @param SourceRange $so
	 */
	public function setSourceOffsets( SourceRange $so ): void {
		$this->applyToStage( 'setSourceOffsets', $so );
	}

	/**
	 * Set frame on this pipeline stage (stages decide if they need it or not)
	 * @param Frame $frame frame
	 */
	public function setFrame( Frame $frame ): void {
		$this->frame = $frame;
		$this->applyToStage( 'setFrame', $frame );
	}

	/**
	 * Process input through the pipeline (potentially skipping the first stage
	 * in case that first stage is the source of input chunks we are processing
	 * in the rest of the pipeline)
	 *
	 * @param string|Token|array<Token|string>|DocumentFragment|Element $input
	 * @param array{sol:bool} $opts
	 *  - sol (bool) Whether tokens should be processed in start-of-line context.
	 *  - chunky (bool) Whether we are processing the input chunkily.
	 *                  If so, the first stage will be skipped
	 * @return array|DocumentFragment|Element
	 */
	public function parse(
		string|Token|array|DocumentFragment|Element $input,
		array $opts
	): array|DocumentFragment|Element {
		$profile = $this->env->profiling() ? $this->env->pushNewProfile() : null;
		if ( $profile !== null ) {
			$profile->start();
		}

		$output = $input;
		foreach ( $this->stages as $i => $stage ) {
			$output = $stage->process( $output, $opts );
		}

		$this->env->getPipelineFactory()->returnPipeline( $this );

		if ( $profile !== null ) {
			$this->env->popProfile();
			$profile->end();

			if ( $this->atTopLevel && $this->pipelineType === 'fullparse-wikitext-to-dom' ) {
				$body = $output;
				$body->appendChild( $body->ownerDocument->createTextNode( "\n" ) );
				$body->appendChild( $body->ownerDocument->createComment( $profile->print() ) );
				$body->appendChild( $body->ownerDocument->createTextNode( "\n" ) );
			}
		}

		return $output;
	}

	/**
	 * Parse input in chunks
	 *
	 * @param string $input Input wikitext
	 * @param array{sol:bool} $opts
	 *  - atTopLevel: (bool) Whether we are processing the top-level document
	 *  - sol: (bool) Whether input should be processed in start-of-line context
	 * @return Element|array final <body> element or array of token chnks
	 */
	public function parseChunkily( string $input, array $opts ) {
		$profile = $this->env->profiling() ? $this->env->pushNewProfile() : null;
		if ( $profile !== null ) {
			$profile->start();
		}

		$ret = [];
		$lastStage = PHPUtils::lastItem( $this->stages );
		foreach ( $lastStage->processChunkily( $input, $opts ) as $output ) {
			$ret[] = $output;
		}

		$this->env->getPipelineFactory()->returnPipeline( $this );

		if ( $profile !== null ) {
			$this->env->popProfile();
			$profile->end();

			if ( $this->atTopLevel && $this->pipelineType === 'fullparse-wikitext-to-dom' ) {
				Assert::invariant( $this->outputType === 'DOM', 'Expected top-level output to be DOM' );
				$body = $ret[0];
				$body->appendChild( $body->ownerDocument->createTextNode( "\n" ) );
				$body->appendChild( $body->ownerDocument->createComment( $profile->print() ) );
				$body->appendChild( $body->ownerDocument->createTextNode( "\n" ) );
			}
		}

		// Return either the DOM or the array of chunks
		return $this->outputType === "DOM" ? $ret[0] : $ret;
	}

	/**
	 * Selective update parts of the old DOM based on $options
	 * $options has additional info about what needs updating.
	 * FIXME: Doucment $options array here.
	 */
	public function selectiveParse(
		SelectiveUpdateData $selparData, array $options
	): Document {
		$dom = $selparData->revDOM;
		$this->parse( DOMCompat::getBody( $dom ), [ 'selparData' => $selparData ] + $options );
		return $dom;
	}

	/**
	 * @param array $initialState Once the pipeline is retrieved / constructed,
	 * it will be initialized with this state.
	 */
	public function init( array $initialState = [] ): void {
		// Reset pipeline state once per top-level doc.
		// This clears state from any per-doc global state
		// maintained across all pipelines used by the document.
		// (Ex: Cite state)
		$this->atTopLevel = $this->alwaysToplevel ?: $initialState['toplevel'];
		$this->resetState( [
			'toplevel' => $this->atTopLevel,
			'toFragment' => $initialState['toFragment'] ?? true,
			'tplInfo' => $initialState['tplInfo'] ?? null,
		] );

		// Set frame
		$frame = $initialState['frame'];
		if ( !$this->atTopLevel ) {
			$tplArgs = $initialState['tplArgs'] ?? null;
			$srcText = $initialState['srcText'] ?? null;
			if ( isset( $tplArgs['title'] ) ) {
				$title = $tplArgs['title'];
				$args = $tplArgs['attribs']; // KV[]
			} else {
				$title = $frame->getTitle();
				$args = $frame->getArgs()->args; // KV[]
			}
			$frame = $frame->newChild( $title, $args, $srcText );
		}
		$this->setFrame( $frame );

		// Set source offsets for this pipeline's content
		$srcOffsets = $initialState['srcOffsets'] ?? null;
		if ( $srcOffsets ) {
			$this->setSourceOffsets( $srcOffsets );
		}
	}
}
