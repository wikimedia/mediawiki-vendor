<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = [
	'src',
	'tests',
	'tools',
	'vendor/consolidation/robo/src',
	'vendor/nikic/php-parser/lib',
	'vendor/phpunit/phpunit/src/Framework',
	'vendor/symfony',
	'vendor/wikimedia/idle-dom/src',
	'vendor/wikimedia/remex-html/RemexHtml/Serializer',
	'vendor/wikimedia/remex-html/RemexHtml/Tokenizer',
	'vendor/wikimedia/remex-html/RemexHtml/TreeBuilder',
	'.phan/stubs',
];
$cfg['suppress_issue_types'] = [];
$cfg['exclude_analysis_directory_list'][] = 'vendor/';
$cfg['exclude_analysis_directory_list'][] = 'tests/W3C/';
$cfg['exclude_analysis_directory_list'][] = 'tests/WPT/';

return $cfg;
