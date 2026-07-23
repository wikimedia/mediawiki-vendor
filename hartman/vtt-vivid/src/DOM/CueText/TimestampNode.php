<?php

namespace WebVTT\DOM\CueText;

/**
 * A WebVTT cue text leaf node holding an in-cue timestamp.
 */
class TimestampNode extends Node {
	private float $time;

	/**
	 * @param float $time The timestamp in seconds.
	 */
	public function __construct( float $time ) {
		$this->time = $time;
	}

	/**
	 * @return float The timestamp in seconds.
	 */
	public function getTime(): float {
		return $this->time;
	}

	public function jsonSerialize(): array {
		return [ 'type' => 'timestamp', 'time' => $this->time ];
	}

	public function toVtt(): string {
		$h = (int)floor( $this->time / 3600 );
		$m = (int)floor( fmod( $this->time, 3600 ) / 60 );
		$s = (int)floor( fmod( $this->time, 60 ) );
		$ms = (int)round( ( $this->time - floor( $this->time ) ) * 1000 );
		return sprintf( '<%02d:%02d:%02d.%03d>', $h, $m, $s, $ms );
	}
}
