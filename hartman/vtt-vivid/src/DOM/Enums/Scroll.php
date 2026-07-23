<?php

namespace WebVTT\DOM\Enums;

/**
 * Represents WebVTT region scroll setting.
 *
 * This enum defines how cues are added to the region.
 * It is used by {@see \WebVTT\DOM\VttRegion::getScroll()} and {@see \WebVTT\DOM\VttRegion::setScroll()}.
 *
 * @link https://www.w3.org/TR/webvtt1/#webvtt-region-scroll-setting
 */
enum Scroll: string {
	/**
	 * Cues are added to the region by being simply placed at the next available line.
	 */
	case NONE = '';

	/**
	 * Cues are added to the region by pushing existing cues up.
	 */
	case UP = 'up';
}
