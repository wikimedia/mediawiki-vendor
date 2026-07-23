The goal of this project is to implement a parser, validator and writer for WebVTT in the PHP language.

The specification for WebVTT is documented at:
- https://www.w3.org/TR/webvtt1
- https://w3c.github.io/webvtt/
- https://developer.mozilla.org/en-US/docs/Web/API/WebVTT_API
- https://github.com/web-platform-tests/wpt/tree/master/webvtt

The project features:
1. A parser.
   - The parser is a stream-based parser. It consumes characters but processes the WebVTT file line by line.
   - The parser can run in strict or in quirks mode
   - The parser fires callbacks that produce the content, error and warning events.
2. A VTT subtitle content parser.
3. File readers and writers

I intend to eventually add a full processor that can render:
- VTT to HTML
- generate img representations of cues
- validate against browser implementations using webdriver

# Examples
## Validating a WebVTT File
You can use the `VttReader` class to validate a WebVTT file.
To enable strict validation, you can configure the `VttReader` to operate in strict mode. Below is an example:

```php
require 'vendor/autoload.php';

use WebVTT\VttReader;

// Load the WebVTT file
$vttFile = 'path/to/your/file.vtt';
$reader = VttReader::fromFile($vttFile);
$reader->setStrict(true);

try {
    $reader->parse();
    echo "The WebVTT file is strictly valid.\n";
} catch (Exception $e) {
    echo "Strict validation failed: " . $e->getMessage() . "\n";
}
```

## Generating a Simple Subtitle File
You can use the `VttWriter` class to generate a WebVTT file. Below is an example:

```php
require 'vendor/autoload.php';

use WebVTT\VttWriter;
use WebVTT\DOM\VttCue;
use WebVTT\DOM\VttFile;

// Create a new VttFile instance
$vttFile = new VttFile();

// Add a cue to the file (start time, end time, text)
$cue = new VttCue(1.0, 4.0, 'Hello, world!');
$vttFile->addBlock($cue);

// Write the file to disk
$outputFile = 'path/to/output/file.vtt';
$writer = new VttWriter($vttFile);
$writer->write($outputFile);

echo "Subtitle file created at $outputFile\n";
```

## Working with cue text

A cue's payload is plain WebVTT cue text, but the library can also expose it as a
tree of typed nodes following the [WebVTT cue text parsing rules](https://www.w3.org/TR/webvtt1/#webvtt-cue-text-parsing-rules).
`VttCue::getContentNodes()` parses the text (once, cached) into:

- `TextNode` — literal text, with the six WebVTT escapes (`&amp;`, `&lt;`, …) decoded;
- `TimestampNode` — an in-cue timestamp such as `<00:00:08.500>`;
- `ElementNode` — a `c`/`i`/`b`/`u`/`ruby`/`rt` tag with its classes and children;
- `AnnotatedElementNode` — a `v` (voice) or `lang` tag, which additionally carries an annotation.

```php
use WebVTT\DOM\VttCue;
use WebVTT\DOM\CueText\AnnotatedElementNode;

$cue = new VttCue(0.0, 5.0, '<v Bob>Hello <b>there</b></v>');
foreach ($cue->getContentNodes() as $node) {
    if ($node instanceof AnnotatedElementNode) {
        echo $node->getAnnotation(); // "Bob"
    }
}
```

You can also author a cue from a node tree with `setContentNodes()`, which keeps
`getText()` and the serialized forms in sync. Both `toCueText()` and casting the cue
(or any node) to a string return the canonical cue text — timestamps and escapes
normalized, unrecognized tags dropped:

```php
$cue = new VttCue(0.0, 5.0, '');
$cue->setContentNodes([ new TextNode('A & B'), new ElementNode(CueTag::BOLD) ]);
echo $cue->toCueText(); // "A &amp; B<b></b>"
```

### Validating cue text directly

`CueTextParser` can be used on its own; pass a `ValidationReporter` to collect
warnings without stopping the parse. It reports unauthorized tags, unclosed /
mismatched / unexpected end tags, `v`/`lang` tags missing their annotation, and class
names that look like unrecognized colours (e.g. suggesting `lime` for `green`).
Nesting is capped at 100 levels so that pathological input cannot exhaust the stack.

```php
use WebVTT\Parser\CueTextParser;
use WebVTT\Validation\CallbackValidationReporter;

$warnings = [];
$parser = new CueTextParser(new CallbackValidationReporter(
    function (string $w) use (&$warnings) { $warnings[] = $w; }
));
$nodes = $parser->parse('<c.green>hi</span>');
// $warnings now describes the colour hint and the unauthorized </span>
```

# Commands

The `bin` directory contains additional command-line tools build enabled by this project:

1. **vtt-validator**: Validates WebVTT files for syntax and structural correctness.
   - **Options**:
     - `--strict`: Enables strict validation mode.
     - `--subtitles`: Validates the text of each cue for proper formatting and reports warnings for invalid content.

2. **vtt-to-json**: Converts WebVTT files to a JSON representation.

# Development
This project builds on the legacy of the pre-standardization vtt.js parser as originally released by Mozilla.
https://github.com/mozilla/vtt.js

Some of its test cases were imported and converted. Some of the approach is also based on this original implementation.

## Requirements
- **PHP**: This project requires PHP version 8.2 or higher.
- **Composer**: Ensure Composer is installed to manage dependencies.

## Installing Dependencies
To install the required dependencies, run the following command:

```bash
composer install
```

This will download and set up all the necessary libraries and packages for the project.

## Linting and Fixing
To ensure code quality and adherence to coding standards, the project includes linting and automatic fixing tools. Use the following commands:

- **Lint the code**:
  ```bash
  composer lint
  ```
  This command checks the codebase for coding standard violations and reports any issues.

- **Fix coding standard violations**:
  ```bash
  composer fix
  ```
  This command attempts to automatically fix coding standard violations where possible.

## Test Cases

The project includes a comprehensive set of test cases to ensure the correctness of the WebVTT parser, validator, and writer. The test cases are organized into the following categories:

1. **Unit Tests**: These tests validate individual components of the project, such as the parser, processor, and validation logic.
2. **Integration Tests**: These tests ensure that the various components work together as expected.
3. **Bin Tests**: These tests verify the functionality of the command-line tools provided in the `bin` directory.

The test cases are located in the `tests` directory and are further organized into subdirectories based on their category.

## Running Tests

To run the test suite, use the following command:

```bash
composer test
```

This command will execute all the test cases and provide a summary of the results.
Ensure that you have Composer installed and the required dependencies set up before running the tests.
You can also run specific categories of tests:

- **Unit Tests**:
  ```bash
  composer test -- --testsuite Unit
  ```
  This command runs only the unit tests.

- **Integration Tests**:
  ```bash
  composer test -- --testsuite Integration
  ```
  This command runs only the integration tests.

- **Bin Tests**:
  ```bash
  composer test -- --testsuite Bin
  ```
  This command runs only the tests for the command-line tools.

#### Running Tests for a Single File

To run tests from a specific file, use the following command:

```bash
composer test -- tests/path/to/YourTestFile.php
```

Replace `tests/path/to/YourTestFile.php` with the relative path to the test file you want to execute.

# License
This project is dual-licensed under the Apache License, Version 2.0 and the MIT License. See the LICENSE file for details.

# Authors
Derk-Jan Hartman <hartman.wiki@gmail.com>
