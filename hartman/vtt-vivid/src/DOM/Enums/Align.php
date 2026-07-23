<?php

namespace WebVTT\DOM\Enums;

/**
 * Represents WebVTT cue alignment.
 *
 * This enum defines the text alignment of the cue's content within the cue box.
 * It is used by {@see \WebVTT\DOM\VttCue::getAlign()} and {@see \WebVTT\DOM\VttCue::setAlign()}.
 *
 * @link https://www.w3.org/TR/webvtt1/#webvtt-alignment-cue-setting
 */
enum Align: string {
	/**
	 * Text is aligned to the start side (left for LTR, right for RTL).
	 */
	case START = 'start';

	/**
	 * Text is centered within the cue box.
	 */
	case CENTER = 'center';

	/**
	 * Text is aligned to the end side (right for LTR, left for RTL).
	 */
	case END = 'end';

	/**
	 * Text is aligned to the left.
	 * @deprecated Use START or END instead for better internationalization.
	 */
	case LEFT = 'left';

	/**
	 * Text is aligned to the right.
	 * @deprecated Use START or END instead for better internationalization.
	 */
	case RIGHT = 'right';

	/**
	 * Text is centered within the cue box.
	 * @deprecated Use CENTER instead.
	 */
	case MIDDLE = 'middle';
}
