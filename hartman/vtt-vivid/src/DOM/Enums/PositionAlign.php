<?php

namespace WebVTT\DOM\Enums;

/**
 * Represents WebVTT cue position alignment.
 *
 * This enum defines the alignment of the cue box relative to the position value.
 * It is used by {@see \WebVTT\DOM\VttCue::getPositionAlign()} and {@see \WebVTT\DOM\VttCue::setPositionAlign()}.
 *
 * @link https://www.w3.org/TR/webvtt1/#webvtt-position-alignment-cue-setting
 */
enum PositionAlign: string {
	/**
	 * Align the cue box's line-left side to the position.
	 */
	case LINE_LEFT = 'line-left';

	/**
	 * Align the center of the cue box to the position.
	 */
	case CENTER = 'center';

	/**
	 * Align the cue box's line-right side to the position.
	 */
	case LINE_RIGHT = 'line-right';

	/**
	 * Automatically determined based on cue text alignment.
	 */
	case AUTO = 'auto';

	/**
	 * Align the cue box's start side to the position.
	 * @deprecated Use LINE_LEFT, CENTER, or LINE_RIGHT instead.
	 */
	case START = 'start';

	/**
	 * Align the cue box's end side to the position.
	 * @deprecated Use LINE_LEFT, CENTER, or LINE_RIGHT instead.
	 */
	case END = 'end';
}
