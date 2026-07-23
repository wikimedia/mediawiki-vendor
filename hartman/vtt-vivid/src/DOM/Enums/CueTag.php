<?php

namespace WebVTT\DOM\Enums;

/**
 * The WebVTT cue text tag names that produce a {@see \WebVTT\DOM\CueText\ElementNode}.
 *
 * @see https://www.w3.org/TR/webvtt1/#webvtt-cue-text-parsing-rules
 */
enum CueTag: string {
	/**
	 * A class tag (&lt;c&gt;): carries classes only.
	 */
	case CSS_CLASS = 'c';

	/**
	 * An italic tag (&lt;i&gt;).
	 */
	case ITALIC = 'i';

	/**
	 * A bold tag (&lt;b&gt;).
	 */
	case BOLD = 'b';

	/**
	 * An underline tag (&lt;u&gt;).
	 */
	case UNDERLINE = 'u';

	/**
	 * A ruby tag (&lt;ruby&gt;).
	 */
	case RUBY = 'ruby';

	/**
	 * A ruby text tag (&lt;rt&gt;).
	 */
	case RUBY_TEXT = 'rt';

	/**
	 * A voice tag (&lt;v&gt;): the annotation is the speaker name.
	 */
	case VOICE = 'v';

	/**
	 * A language tag (&lt;lang&gt;): the annotation is the language.
	 */
	case LANGUAGE = 'lang';
}
