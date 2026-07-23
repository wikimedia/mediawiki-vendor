<?php

namespace WebVTT\DOM\Enums;

/**
 * Represents WebVTT cue vertical direction.
 *
 * This enum defines the writing direction of the cue.
 * It is used by {@see \WebVTT\DOM\VttCue::getVertical()} and {@see \WebVTT\DOM\VttCue::setVertical()}.
 *
 * @link https://www.w3.org/TR/webvtt1/#webvtt-vertical-text-cue-setting
 */
enum Direction: string {
	/**
	 * Horizontal text (default).
	 */
	case HORIZONTAL = '';

	/**
	 * Vertical left-to-right.
	 */
	case LR = 'lr';

	/**
	 * Vertical right-to-left.
	 */
	case RL = 'rl';
}
