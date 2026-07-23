<?php

namespace WebVTT\Parser;

/**
 * Represents the current state of the WebVTT parser.
 */
enum ParserState: string {
	case INITIAL = 'INITIAL';
	case HEADER = 'HEADER';
	case REGION = 'REGION';
	case STYLE = 'STYLE';
	case NOTE = 'NOTE';
	case BLOCK = 'BLOCK';
	case CUE = 'CUE';
	case CUETEXT = 'CUETEXT';
	case BADCUE = 'BADCUE';
	case BADWEBVTT = 'BADWEBVTT';
}
