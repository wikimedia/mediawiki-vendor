# Release History

# 4.5.2 (2026-07-03)
* Command: Set default for $workingDirectory (Sam Reed)

# 4.5.1 (2026-07-03)
* Command: Set defaults for more properties (Sam Reed)
* Command: Treat null includeStderr as false in setClientData (Scott French)
* Rebuild images for package updates (Scott French)
* Rebuild score image with lilypond 2.24 (has cairo) (Raine Souček)
* composer.lock: Update (Sam Reed)

## 4.5.0 (2026-05-12)
* Raise guzzlehttp/guzzle to ^7.10.0 (James D. Forrester)
* Cleanup and code modernisation (Sam Reed)
* tests: Minor cleanup and updates (Sam Reed)
* Restore some code coverage (Reedy)
* composer.json: Stop running @phan in composer test (Sam Reed)
* composer.lock: Update (Sam Reed)
* BuiltinServerManager: Minor cleanup and drop Xdebug2 (Sam Reed)
* composer.json: Raise requirements to PHP >= 8.3 (Sam Reed)
* build: Updating mediawiki/mediawiki-codesniffer to 51.0.0 (libraryupgrader)
* Declare strict types on all php files (Umherirrender)
* Client: Include truncated body content on server error (Umherirrender)
* Remove strlen() calls as false check in Server::setupLogger (Umherirrender)
* build: Don't list json as a required extension (Umherirrender)
* build: Updating mediawiki/mediawiki-phan-config to 0.20.0 (libraryupgrader)
* build: Remove unused build/doxygen_escape.sh, enable Phan in CI (Timo Tijhof)
* build: Switch from releng/composer-package-php83 to releng/composer-php83 (Timo Tijhof)
* Rebuild images for package updates (Scott French)
* build: Updating mediawiki/mediawiki-codesniffer to 50.0.0 (libraryupgrader)
* build: Upgrade mediawiki-phan-config to 0.19.0 for PHP 8.5 support (James D. Forrester)
* build: Move phan out of main test and into own job (James D. Forrester)
* composer.json: Upgrade to monolog/monolog ^3.0.0 (Reedy)
* Upgrading phpunit/phpunit (9.6.34 => 10.5.63) (Sam Reed)
* Upgrading mediawiki/mediawiki-codesniffer (v48.0.0 => v49.0.0) (Sam Reed)
* Upgrading mediawiki/minus-x (1.1.3 => 2.0.1) (Sam Reed)
* Drop PHP 8.1 support (Sam Reed)
* ClientTest: Stop passing E_USER_ERROR to trigger_error() (Sam Reed)
* tests: use static data provider in RpcClientTests trait (Umherirrender)
* UnboxedExecutor: Add logging pid for cmd (Clément Goubert)
* dev: Allow overriding the local httpd server port (Bryan Davis)
* composer.json: Re-enable block-insecure (Reedy)
* build: Upgrade mediawiki/mediawiki-phan-config from 0.17.0 to 0.18.0 (James D. Forrester)
* Upgrading psy/psysh (v0.10.12 => v0.12.19) (Reedy)
* build: Upgrade PHPUnit from 9.6.21 to 9.6.34 to unblock CI (James D. Forrester)
* Temporarily remove monolog/monolog pin to ^3.0.0 (Reedy)
* Catch ConnectExceptions of remote download/upload of files (Derk-Jan Hartman)
* Rebuild images for package updates (Scott French)
* build: Switch buildkit from v0.21.1 to v1.6.0 (Scott French)
* Rebuild images following base image rebuild (Scott French)

## 4.4.0 (2025-12-18)
* Require PHP 8.1 or later, drop support for PHP 7.4 and 8.0. (James D. Forrester)
* composer: Allow psr/log ^3.0.0 (James D. Forrester) [T356451](https://phabricator.wikimedia.org/T356451)
* composer: Allow monolog/monolog ^3.0.0 (Reedy)
* composer: Update wikimedia/wikipeg to 6.0.0 (Arlo Breault, C. Scott Ananian)

## 4.3.0 (2025-05-27)
* Improve function documentation in BoxedExecutorTestTrait
* composer: Allow wikimedia/wikipeg 5.0.0

## 4.2.0 (2025-03-27)
* Replace call_user_func_array with dynamic function call
* Document bubbled ClientExceptionInterface (T374117)

## 4.1.2 (2025-01-07)
* Check that error level should be handled before throwing. Allows expected
  warnings and errors to be suppressed, rather than triggering a ShellboxError
* Pass pcov options to child process

## 4.1.1 (2024-10-29)
* composer: Update guzzle/guzzlehttp to 7.9.2 (Reedy)
* Use explicit nullable type on parameter arguments (Reedy)

## 4.1.0 (2024-10-17)
* Add remote download/upload support for large file performance (T292322). The
  feature is off by default. Once the server is updated and has allowUrlFiles
  enabled, the client can enable allowUrlFiles to fully enable the feature for
  callers.
* Fix bug in BoxedCommand::inputFileFromStream.
* composer: Require wikimedia/wikipeg 4.0.0.

## 4.0.2 (2024-03-05)
* composer: Allow wikimedia/wikipeg 4.0.0.

## 4.0.1 (2023-02-20)
* In the Firejail wrapper, fix handling of empty environment variables.
* Fix compatibility with Firejail 0.9.72 and fix a possible sandbox escape by
  passing the whole command string to a shell that runs under Firejail.

## 4.0.0 (2022-11-10)
* Require PHP >=7.4.3, up from 7.2.9.
* Fix OOM in MultipartAction when handling a large request body.
* Compatibility with php8.1: Use ENT_COMPAT on htmlspecialchars.
* Allow the use of wikimedia/wikipeg 3.0.0.

## 3.0.0 (2021-11-04)
* Add RpcClient interface for remote code execution and a LocalRpcClient
  implementation to be used as fallback when Shellbox server is not
  available.
* PSR-18 ClientInterface is now used instead of Shellbox own HttpClientInterface

## 2.1.1 (2022-07-27)
* Loosen guzzlehttp/guzzle requirement.

## 2.1.0 (2021-09-24)
* Roll our own Unix-like shell escaping function, improving PHP 8 support
  while still protecting against attacks using GBK locales by filtering
  them out of the environment.

## 2.0.0 (2021-08-20)

* Require PHP >=7.2.9, up from 7.2.0.
* Add a "spec" action, which exposes a swagger spec for service-checker.
* Remove non-functional allowlist code (T277981).
* Documentation has been added to <https://www.mediawiki.org/wiki/Shellbox>.

## 1.0.4 (2021-02-26)

* Raise priority of limit.sh if it uses a cgroup (T274942).

## 1.0.3 (2021-02-10)

* Only allow path to limit.sh when command has other allowed paths (T274474).
* build: Exclude config folder from Composer package.

## 1.0.2 (2021-02-07)

* Don't pass through the fake CGI environment to subprocesses.

## 1.0.1 (2021-02-03)

* build: Exclude public_html folder from Composer package.

## 1.0.0 (2021-02-02)

* Initial release.
