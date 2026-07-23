<?php

namespace WebVTT\DOM\Enums;

/**
 * Represents WebVTT cue line alignment.
 *
 * This enum defines how the line is aligned within the cue box.
 * It is used by {@see \WebVTT\DOM\VttCue::getLineAlign()} and {@see \WebVTT\DOM\VttCue::setLineAlign()}.
 *
 * @link https://www.w3.org/TR/webvtt1/#webvtt-line-alignment-cue-setting
 */
enum LineAlign: string {
	/**
	 * Line box is aligned to the start side.
	 */
	case START = 'start';

	/**
	 * Line box is centered.
	 */
	case CENTER = 'center';

	/**
	 * Line box is aligned to the end side.
	 */
	case END = 'end';
}
