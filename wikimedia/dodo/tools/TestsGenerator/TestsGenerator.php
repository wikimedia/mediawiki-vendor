<?php

declare( strict_types=1 );

namespace Wikimedia\Dodo\Tools\TestsGenerator;

use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Exception;
use Robo\Common\IO;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Tasks;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * Class TestsGenerator
 */
class TestsGenerator extends Tasks {

	use LoadTasks;
	use \Robo\Task\Testing\Tasks;
	use \Robo\Task\File\Tasks;
	use \Robo\Task\Filesystem\Tasks;
	use \Robo\Task\Base\Tasks;
	use \Robo\Task\Npm\Tasks;
	use IO;
	use Helpers;

	public const W3C = "W3C";
	public const WPT = "WPT";

	private const W3C_TESTS = "/vendor/fgnass/domino/test/w3c";

	private const WPT_TESTS = "/vendor/web-platform-tests/wpt";

	/**
	 * @var string
	 */
	public $folder;

	/**
	 * @var string
	 */
	public $root_folder;

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var array
	 */
	private $tests;

	/**
	 * @var bool
	 */
	private $debug;

	/**
	 * TestsGenerator constructor.
	 */
	public function __construct() {
		$this->debug = true;
		$this->filesystem = new Filesystem();
		$this->folder = __DIR__;
		$this->root_folder = realpath( $this->folder . "/../.." );
		$this->tests = [];
	}

	/**
	 * Main task
	 *
	 * @param array $opts
	 * - rewrite
	 * - phpcbf
	 * - run
	 * - compact = generates all tests in one file
	 */
	public function build( array $opts = [
			'rewrite' => true,
			'limit' => -1,
			'phpcbf' => true,
			'run' => false,
			'compact' => false,
	] ) : void {
		try {
			$compact_tests = '';
			// Init and check for dependencies.
			$this->initDependencies( $opts['rewrite'] );

			$this->stopOnFail( false );

			// locate W3C and WPT tests
			$result = $this->taskTestsLocator( $this->root_folder )->run();

			if ( !$result->wasSuccessful() || $result->wasCancelled() ) {
				throw new TaskException( $this, $result->getMessage() );
			}

			$files = $result->getData();

			if ( empty( $files ) ) {
				throw new TaskException( $this, 'No tests were loaded.' );
			}

			foreach ( $result->getData() as $test_type => $tests ) {
				if ( $test_type === 'time' ) {
					continue;
				}

				if ( $test_type === self::WPT ) {
					$opts['compact'] = false;
				}

				$tests_per_type = $opts['limit'];

				foreach ( $tests as $file ) {
					if ( $tests_per_type-- === 0 ) {
						break;
					}

					$test_name = $file->getFilenameWithoutExtension();
					$new_test_name = $this->snakeToPascal( $test_name );

					// TODO comment
					$test_path = $this->root_folder . ( $test_type === self::W3C ? self::W3C_TESTS : self::WPT_TESTS );
					$test_path = str_replace(
						$test_path,
						'',
						realpath( $file->getPath() ) );

					// eg. /level1/core -> /Level1/Core
					$test_path = ucwords( $test_path,
						'/' );
					$test_path = "{$this->root_folder}/tests/{$test_type}{$test_path}/{$new_test_name}Test.php";

					/**
					 * Skip test if it's already generated and there is no --rewrite arg provided,
					 * also skip hard to parse tests
					 */
					if ( !$opts['rewrite'] && $this->filesystem->exists( $test_path ) ) {
						$this->say( 'Skipped ' . $test_name );
						continue;
					}

					$actual_test = $this->transpileFile( $file );

					if ( empty( $actual_test ) ) {
						$this->say( 'Skipped ' . $file );
						continue;
					}

					$actual_test = $this->taskParseTest( $actual_test,
						$test_name,
						$test_type,
						$opts['compact'],
						false,
						$file->getRealPath() )->run();

					if ( !$actual_test->wasSuccessful() ) {
						throw new TaskException( $this, $actual_test->getMessage() );
					}

					if ( $opts['compact'] ) {
						$compact_tests .= $actual_test->getData()[0];
					} else {
						$phpUnitTest = $actual_test->getData()[0];
						$this->writeTest( $test_path,
							$phpUnitTest );
					}
				}

				// If compact mode is on.
				if ( $opts['compact'] && !empty( $compact_tests ) ) {
					$actual_test = $this->taskParseTest( $compact_tests,
						$test_type,
						$test_type,
						true,
						true )->run();

					if ( !$actual_test->wasSuccessful() ) {
						throw new TaskException( $this, $actual_test->getMessage() );
					}
					$phpUnitTest = $actual_test->getData()[0];
					$test_path = "{$this->root_folder}/tests/{$this->snakeToPascal($test_type)}Test.php";
					$this->writeTest( $test_path,
						$phpUnitTest );
					$compact_tests = '';
				}
			}

			// Run phpcbf.
			if ( $opts['phpcbf'] ) {
				$this->taskExec( 'composer fix' )->run();
			}

			// Copy html files for tests.
			$result = $this->copyFiles();
			if ( !$result->wasSuccessful() || $result->wasCancelled() ) {
				throw new TaskException( $this, $result->getMessage() );
			}

			// Run phpunit.
			if ( $opts['run'] ) {
				// Regenerate autoload file.
				$result = $this->taskExec( 'composer dump' )->run();

				if ( !$result->wasSuccessful() || $result->wasCancelled() ) {
					throw new TaskException( $this, $result->getMessage() );
				}

				// Run tests.
				$result = $this->taskExec( 'composer phpunit' )->run();
				if ( !$result->wasSuccessful() || $result->wasCancelled() ) {
					throw new TaskException( $this, $result->getMessage() );
				}

				$this->logProcess();
			}
		} catch ( TaskException | Exception $e ) {
			$this->yell( $e->getFile() . ':' . $e->getMessage(),
				100,
				'red' );
			$this->yell( $e->getTraceAsString(),
				100,
				'red' );
		}
	}

	/**
	 * Checks for if everything neccesary for test generation was installed.
	 * If not - run npm install, composer install or throw an exception
	 *
	 * @param bool $rewrite
	 *
	 * @throws TaskException
	 */
	public function initDependencies( bool $rewrite = false ) : void {
		// Check if js2php was installed.
		if ( !$this->taskExecStack()->stopOnFail()->dir( $this->root_folder )->exec( 'npm list | grep js2php' )
			->printOutput( false )->run()->getMessage() ) {

			$this->taskNpmInstall()->run();
		}

		// Check if Domino was installed.
		if ( !$this->filesystem->exists( $this->root_folder . '/tests/W3C' ) ) {
			$domino_path = $this->root_folder . '/vendor/fgnass/domino';
			if ( !$this->filesystem->exists( $domino_path ) ) {
				if ( !$this->taskComposerInstall()->dev( true )->run()->wasSuccessful() ) {
					throw new TaskException( $this, 'No DominoJS found.' );
				}
			}
		}

		// Check if web-platform-tests was installed.
		if ( !$this->filesystem->exists( $this->root_folder . '/tests/WPT' ) ) {
			$wpt_path = $this->root_folder . '/vendor/web-platform-tests/wpt';
			if ( !$this->filesystem->exists( $wpt_path ) && !$this->taskComposerInstall()->dev( true )->run()
					->wasSuccessful() ) {
				throw new TaskException( $this,
					'No WPT tests found.' );
			}
		}
	}

	/**
	 * Transpiles a file.
	 * TODO rewrite this mess.
	 *
	 * @param SplFileInfo $file
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function transpileFile( SplFileInfo $file ) : string {
		$file_path = $file->getRealPath();
		$remove = false;
		$other_scripts = [];

		if ( $file->getExtension() === 'html' ) {
			$file_content = $file->getContents();
			preg_match_all( '#<script>(.*?)</script>#is',
				$file_content,
				$matches );

			preg_match_all( '/<script src="(.*)"><\/script>/',
				$file_content,
				$includes );

			if ( !empty( $includes[1] ) ) {
				$defaults = [ '/resources/testharness.js',
					'/resources/testharnessreport.js', ];

				$scripts_diff = array_diff( $includes[1],
					$defaults );

				if ( $scripts_diff ) {
					foreach ( $scripts_diff as $script ) {
						$sf = $file->getPath() . '/' . $script;
						if ( $this->filesystem->exists( $sf ) ) {
							$other_scripts[] = file_get_contents( $sf );
						}
					}
				}
			}

			$other_scripts = implode( '',
				$other_scripts );

			if ( !empty( $matches[1] ) ) {
				$content = implode( '',
					$matches[1] ); // without <script> tag
				$file_path = $this->_tmpDir() . '/' . $file->getFilename();
				$this->taskWriteToFile( $file_path )->text( $content . $other_scripts )->run();
				$remove = true;
			} else {
				return '';
			}
		}

		$result = $this->taskExec( 'npm run js2php' )->arg( $file_path )->dir( $this->root_folder )
			->printOutput( false )->run();

		if ( $result->wasSuccessful() ) {
			if ( $remove ) {
				$this->_remove( $file_path );
			}

			return preg_replace( '#(.*?)<?php#is',
				'',
				$result->getMessage() );
		}

		throw new Exception( sprintf( 'Failed to parse %s',
			$file_path ) );
	}

	/**
	 * @param string $test_name
	 * @param string $test_code
	 *
	 * @return Result
	 */
	public function writeTest( string $test_name, string $test_code ) : Result {
		return $this->taskWriteToFile( $test_name )->text( $test_code )->run();
	}

	/**
	 * Copies html files for testing
	 *
	 * @return Result
	 */
	public function copyFiles() : Result {
		$w3c_core = $this->root_folder . self::W3C_TESTS . '/level1/core/files/*.html';
		$w3c_html = $this->root_folder . self::W3C_TESTS . '/level1/html/files/*.html';

		$cp_dirs = [
			$w3c_core => $this->root_folder . '/tests/' . self::W3C . '/Level1/Core/files/',
			$w3c_html => $this->root_folder . '/tests/' . self::W3C . '/Level1/Html/files/' ];

		return $this->taskFlattenDir( $cp_dirs )->run();
	}

	/**
	 * Converts file paths to relative.
	 */
	public function logProcess() : void {
		$log_folder = $this->root_folder . '/tests/logs/';
		$log_file_original = $log_folder . 'log.xml';
		$log_file_proc = $log_folder . 'log.yml';

		/** Remove environment specific path from log entries. */
		if ( !$this->filesystem->exists( [ $log_file_original ] ) ) {
			$this->yell( 'No PHPUnit log found.',
				40,
				'red' );

			return;
		}

		$log_content = file_get_contents( $log_file_original );
		$log_content = str_replace( $this->root_folder,
			'',
			$log_content );

		$this->taskWriteToFile( $log_file_original )->text( $log_content )->run();

		/** Generate readable errors list */
		$error_list_file = $log_folder . 'errors.yaml';
		$errors_cause = $this->filesystem->exists( $error_list_file ) ? Yaml::parseFile( $error_list_file ) : [];

		// Parsing log.xml
		$document = new DOMDocument;
		$document->loadxml( $log_content );
		$xpath = new DOMXPath( $document );

		// Extracting errors
		$test_suites = $xpath->evaluate( '//error/..' );
		$errors_list = $this->extractSuites(
			$test_suites,
			"/\R(.*)\R\R/U",
			$errors_cause
		);

		$this->taskWriteToFile( $error_list_file )->text( Yaml::dump( $errors_list,
			2,
			4,
			Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE ) )->run();

		// remove processed items
		$this->removeSuites( $test_suites );

		// Extracting failures
		$failure_list_file = $log_folder . 'failures.yml';
		$failures_cause = $this->filesystem->exists( $failure_list_file ) ? Yaml::parseFile( $failure_list_file ) : [];
		$failures = $xpath->evaluate( '//failure/../..' );

		foreach ( $failures as $failure ) {
			$textContent = trim( $failure->textContent );

			if ( strpos( $textContent, "---" ) !== false ) {
				$textContent = substr_replace(
					$textContent,
					'',
					strpos( $textContent, '---' )
				);
			}

			$textContent = preg_replace(
				'/' . preg_quote( 'Wikimedia\\Dodo\\Tests\\', '/' ) . '(.*)\R/',
				'',
				$textContent
			);

			if ( strpos( $textContent, "\n\n" ) !== false ) {
				$textContent = substr_replace(
					$textContent,
					'',
					strpos( $textContent, "\n\n" )
				);
			}

			$textContent = trim( $textContent );
			$textContent = str_replace(
				"\n",
				"-",
				$textContent
			);
			$textContent = preg_replace(
				'/\s+/',
				' ',
				$textContent
			);
			$failure->textContent = $textContent;
		}

		$failures_list = $this->extractSuites(
			$failures,
			"/(.*)/",
			$failures_cause
		);

		$this->taskWriteToFile( $log_folder . 'failures.yml' )->text( Yaml::dump( $failures_list,
			2,
			4,
			Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE ) )->run();

		/** Skips */
		$skips_list_file = $log_folder . 'skipped.yaml';
		$skips_causes = $this->filesystem->exists( $skips_list_file ) ? Yaml::parseFile( $skips_list_file ) : [];
		$skipped = array_flip( LocatorTask::$skips );

		foreach ( $skipped as $reason => $file ) {
			$_ = array_keys( LocatorTask::$skips,
				$reason );
			$skipped[$reason] = [ '_total' => count( $_ ),
				'_comment' => $skips_causes[$reason]['_comment'] ?? '',
				'suites' => implode( PHP_EOL,
					$_ ), ];
		}
		ksort( $skipped );
		$skipped_file = $log_folder . 'skipped.yaml';

		$this->taskWriteToFile( $skipped_file )->text( Yaml::dump( $skipped,
			2,
			4,
			Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE ) )->run();

		// remove processed items from log.xml
		$this->taskWriteToFile( $log_folder . '_log.xml' )->text( $document->saveXml() )->run();
	}

	/**
	 * @param DOMNodeList|null $test_cases
	 * @param string $pattern
	 * @param mixed $existing_log
	 *
	 * @return array|void|null
	 */
	private function extractSuites( ?DOMNodeList $test_cases, string $pattern, $existing_log ) {
		$errors_cause = [];

		if ( !$test_cases ) {
			$this->yell( 'No test suites are provided.',
				40,
				'red' );

			return;
		}

		foreach ( $test_cases as $test_case ) {
			preg_match( $pattern,
				$test_case->textContent,
				$matches );
			if ( !empty( $matches ) && isset( $matches[0] ) ) {
				# replace object memory address, because it varies every run
				$matches[0] = preg_replace(
					'/(Object|Array) &[0-9a-f]*/', '$1',
					trim( $matches[0] )
				);
				$existing_entry = $existing_log[$matches[0]] ?? [];
				$files = $errors_cause[$matches[0]]['files'] ?? [];
				$cases = $errors_cause[$matches[0]]['testcases'] ?? [];

				$case = $test_case->getAttribute( 'name' );
				$file = $test_case->getAttribute( 'file' );

				if ( !in_array( $file,
					$files,
					false ) ) {
					$files[] = $file;
				}

				if ( !in_array( $case,
					$cases,
					false ) ) {
					$cases[] = $case;
				}

				$errors_cause[$matches[0]] = [ '_total' => -1,
					'_comment' => $existing_entry['_comment'] ?? '',
					'testcases' => $cases,
					'files' => $files ];
			}
		}

		/** Count totals and implode */
		foreach ( $errors_cause as &$cause ) {
			$cause['_total'] = count( $cause['testcases'] );
			sort( $cause['testcases'], SORT_NATURAL );
			$cause['testcases'] = implode( PHP_EOL,
				$cause['testcases'] );
			sort( $cause['files'], SORT_NATURAL );
			$cause['files'] = implode( PHP_EOL,
				$cause['files'] );
		}
		ksort( $errors_cause );

		return $errors_cause;
	}

	/**
	 * @param DOMNodeList $test_suites
	 */
	protected function removeSuites( DOMNodeList $test_suites ) : void {
		foreach ( $test_suites as $suite ) {
			$parent = $suite->parentNode;

			if ( $parent->parentNode ) {
				$parent->parentNode->removeChild( $parent );
			} else {
				$parent->removeChild( $suite );
			}
		}
	}

	/**
	 * @param string $test
	 * @param string $method
	 *
	 * @return Result
	 */
	protected function runTest( string $test, string $method ) : Result {
		return $this->taskPhpUnit()->file( $test )->filter( $method )->run();
	}
}
