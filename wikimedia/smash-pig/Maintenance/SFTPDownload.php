<?php

namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use SmashPig\Core\Logging\Logger;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * SFTPDownload
 *
 * Download files from an SFTP directory that are not already present in local
 * directories (comparison by basename). Downloads always go to the incoming directory.
 *
 * Local directory structure expectation:
 *   <base>/incoming   (download destination)
 *   <base>/ignored    (files intentionally skipped)
 *   <base>/completed  (successfully processed files)
 *
 * If only --incoming-directory is provided, ignored/completed are inferred as siblings:
 *   ignored-directory   = dirname( incoming-directory ) . '/ignored'
 *   completed-directory = dirname( incoming-directory ) . '/completed'
 *
 * Optional config file (YAML) support (see config.example.yaml):
 *   incoming_path: /var/spool/audit/dlocal/incoming
 *   sftp:
 *     host: 127.0.0.1
 *     username: foo
 *     remote_root: /reports
 *     private_key: |-
 *       -----BEGIN RSA PRIVATE KEY-----
 *       ...
 *       -----END RSA PRIVATE KEY-----
 *     host_key: |-
 *       ssh-ed25519 AAA...
 *     # optional:
 *     password: yourPasswordHere
 *
 * Notes:
 * - If the ignored directory does not exist, it is treated as empty (not an error).
 * - Verification (default on) compares remote vs downloaded size before finalizing.
 * - reject-empty (default on) removes zero-byte downloads and can optionally panic.
 * - Host key pinning is supported via config sftp.host_key (must match getServerPublicHostKey()).
 * - --limit selects the most recent eligible remote files first (descending mtime).
 */
class SFTPDownload extends MaintenanceBase {

	/**
	 * @var array
	 */
	private array $config;

	/**
	 * @var \phpseclib3\Net\SFTP
	 */
	private SFTP $sftp;

	/**
	 * @throws \SmashPig\Core\SmashPigException
	 */
	public function __construct() {
		parent::__construct();

		// Local paths
		$this->addOption( 'incoming-directory', 'Local incoming directory (download destination)', null, 'i' );
		$this->addOption( 'ignored-directory', 'Local ignored directory (default: sibling of incoming-directory)', null, 'g' );
		$this->addOption( 'completed-directory', 'Local completed directory (default: sibling of incoming-directory)', null, 'c' );

		// Additional local roots to check (repeatable)
		$this->addOption(
			'extra-path',
			'Additional local directory to scan for existing files (repeatable)',
			null,
			'x',
		);

		// Config loading
		$this->addOption(
			'config-name',
			'Config name to load (looks for /etc/fundraising/<name>.yaml or $HOME/.fundraising/<name>.yaml)',
		);
		$this->addOption( 'config', 'Explicit path to config yaml' );

		// Remote connection
		$this->addOption( 'connect-string', 'SFTP connect string (user@host)', null, 's' );
		$this->addOption( 'password-file', 'File containing SFTP password', null, 'p' );
		$this->addOption( 'private-key-file', 'Path to private key file (PEM/OpenSSH). Overrides config.' );
		$this->addOption( 'private-key-password', 'Passphrase for private key file (if encrypted)' );
		$this->addOption( 'remote-directory', 'Remote directory to download from (or sftp.remote_root in config)', null, 'r' );

		$this->addOption( 'port', 'SFTP port', 22 );
		$this->addOption( 'timeout', 'Connection timeout (seconds)', 30 );

		// Selection controls
		$this->addOption( 'limit', 'Maximum number of files to download (most recent first). 0 = no limit.', 0, 'l' );
		$this->addOption( 'filter', 'Only download remote files whose names contain this substring', null, 'f' );

		// Behavior toggles
		$this->addOption( 'dry-run', 'Log actions but do not download or delete', false, 'n' );
		$this->addOption( 'verify', 'Verify download by comparing remote vs local size', true );

		$this->addOption( 'reject-empty', 'Reject empty files: delete locally and record failure', true );
		$this->addOption( 'panic-on-empty', 'If any empty files are seen, exit with error', false );

		$this->addOption( 'delete-remote', 'Delete remote files after verified download (subject to age threshold)', false, 'd' );
		$this->addOption(
			'delete-remote-age-days',
			'Minimum remote file age in days before deletion is allowed (default 7)',
			7
		);
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute(): void {
		$this->config = $this->loadYamlConfig();

		$incomingOptOrConfig = $this->chooseOptionOrConfig( 'incoming-directory', 'incoming_path', '' );
		if ( !is_string( $incomingOptOrConfig ) || trim( $incomingOptOrConfig ) === '' ) {
			$this->error( 'incoming-directory is required (or set incoming_path in config).' );
		}

		$incomingDir = $this->normalizePath( $incomingOptOrConfig );
		$incomingDir = $this->maybeRealpath( $incomingDir );

		$ignoredDir = $this->normalizePath( (string)$this->getOption( 'ignored-directory' ) );
		$completedDir = $this->normalizePath( (string)$this->getOption( 'completed-directory' ) );

		// completed: infer sibling if not provided
		if ( $completedDir === '' ) {
			$completedDir = $this->inferSiblingDir( $incomingDir, 'completed' );
		} else {
			$completedDir = $this->maybeRealpath( $completedDir );
		}

		// ignored: infer sibling if not provided; if missing, treat as empty (not an error)
		if ( $ignoredDir === '' ) {
			$ignoredDir = $this->inferSiblingDir( $incomingDir, 'ignored' );
		} else {
			$ignoredDir = $this->maybeRealpath( $ignoredDir );
		}

		$remoteDirOptOrConfig = $this->chooseOptionOrConfig( 'remote-directory', 'sftp.remote_root', '' );
		if ( !is_string( $remoteDirOptOrConfig ) || trim( $remoteDirOptOrConfig ) === '' ) {
			$this->error( 'remote-directory is required (or set sftp.remote_root in config).' );
		}
		$remoteDir = rtrim( $remoteDirOptOrConfig, '/' );

		$connectString = (string)$this->getOption( 'connect-string' );
		if ( $connectString === '' ) {
			$host = $this->getFromConfig( 'sftp.host', '' );
			$username = $this->getFromConfig( 'sftp.username', '' );

			if ( !is_string( $host ) || trim( $host ) === '' || !is_string( $username ) || trim( $username ) === '' ) {
				$this->error( 'connect-string is required (or set sftp.host and sftp.username in config).' );
			}

			$connectString = trim( $username ) . '@' . trim( $host );
		}

		$dryRun = $this->asBool( $this->getOption( 'dry-run' ) );
		$verify = $this->asBool( $this->getOption( 'verify' ) );
		$rejectEmpty = $this->asBool( $this->getOption( 'reject-empty' ) );
		$panicOnEmpty = $this->asBool( $this->getOption( 'panic-on-empty' ) );

		$deleteRemote = $this->asBool( $this->getOption( 'delete-remote' ) );
		$deleteAgeDays = (int)$this->getOption( 'delete-remote-age-days' );
		if ( $deleteAgeDays < 0 ) {
			$this->error( 'delete-remote-age-days must be >= 0' );
		}
		$deleteCutoff = time() - ( $deleteAgeDays * 24 * 60 * 60 );

		$limit = (int)$this->getOption( 'limit' );
		if ( $limit < 0 ) {
			$this->error( 'limit must be >= 0' );
		}

		$filter = trim( (string)$this->getOption( 'filter' ) );
		$filter = $filter !== '' ? $filter : null;

		// Validate required local dirs
		$this->validateDir( $incomingDir, true );

		// Optional dirs: missing ignored should not matter; missing completed treated as empty too
		$ignoredExists = is_dir( $ignoredDir );
		$completedExists = is_dir( $completedDir );

		if ( $ignoredExists ) {
			$this->validateDir( $ignoredDir, false );
		} else {
			Logger::info( "Ignored directory not found; treating as empty: $ignoredDir" );
		}

		if ( $completedExists ) {
			$this->validateDir( $completedDir, false );
		} else {
			Logger::info( "Completed directory not found; treating as empty: $completedDir" );
		}

		$extraPaths = $this->normalizeExtraPaths( $this->getOption( 'extra-path' ) );

		$dirsToScan = [ $incomingDir ];
		if ( $ignoredExists ) {
			$dirsToScan[] = $ignoredDir;
		}
		if ( $completedExists ) {
			$dirsToScan[] = $completedDir;
		}
		foreach ( $extraPaths as $extraPath ) {
			if ( is_dir( $extraPath ) ) {
				$dirsToScan[] = $extraPath;
			} else {
				Logger::info( "Extra path not found; treating as empty: $extraPath" );
			}
		}

		$password = $this->readPasswordFromConfigOrFile();
		$privateKey = $this->loadPrivateKey();

		[ $host, $username ] = $this->parseConnectString( $connectString );

		Logger::info( "Connecting to $username@$host" );

		$this->sftp = new SFTP(
			$host,
			(int)$this->getOption( 'port' ),
			(int)$this->getOption( 'timeout' )
		);

		// Algorithm preferences for interoperability.
		$this->configureSshAlgorithmDiscovery();

		// Host key pinning (if configured)
		$this->verifyPinnedHostKeyOrError( $host );

		// Auth precedence: password first, else private key
		if ( $password !== null ) {
			if ( !$this->sftp->login( $username, $password ) ) {
				$this->error( "SFTP login failed for $username@$host (password)" );
			}
		} elseif ( $privateKey !== null ) {
			Logger::info( 'Private key loaded (' . get_class( $privateKey ) . ').' );
			if ( !$this->sftp->login( $username, $privateKey ) ) {
				$this->error( "SFTP login failed for $username@$host (private key)" );
			}
		} else {
			$this->error( 'No authentication available: provide --password-file, config sftp.password, or config sftp.private_key.' );
		}

		$this->logNegotiatedAlgorithms();

		$remoteEntries = $this->listRemoteFilesWithMtime( $remoteDir );
		$localBasenames = $this->collectBasenames( $dirsToScan );

		// Filter + skip anything already present locally.
		$candidates = [];
		$skipped = 0;

		foreach ( $remoteEntries as $entry ) {
			$name = $entry['name'];

			if ( $name === '.' || $name === '..' ) {
				$skipped++;
				continue;
			}

			if ( !$this->isSafeBasename( $name ) ) {
				Logger::warning( "Skipping unsafe remote entry name: $name" );
				$skipped++;
				continue;
			}

			if ( $filter !== null && !str_contains( $name, $filter ) ) {
				$skipped++;
				continue;
			}

			if ( isset( $localBasenames[$name] ) ) {
				$skipped++;
				continue;
			}

			$candidates[] = $entry;
		}

		// Sort most recent first (mtime desc), then name for stability.
		usort( $candidates, static function ( array $a, array $b ): int {
			if ( $a['mtime'] === $b['mtime'] ) {
				return strcmp( $a['name'], $b['name'] );
			}
			return ( $a['mtime'] > $b['mtime'] ) ? -1 : 1;
		} );

		if ( $limit > 0 && count( $candidates ) > $limit ) {
			$candidates = array_slice( $candidates, 0, $limit );
		}

		$flags = [];
		if ( $dryRun ) {
			$flags[] = 'DRY RUN';
		}
		if ( $deleteRemote ) {
			$flags[] = "DELETE REMOTE AFTER VERIFY (AGE >= {$deleteAgeDays}d)";
		}
		if ( !$verify ) {
			$flags[] = 'NO VERIFY';
		}
		if ( $rejectEmpty ) {
			$flags[] = 'REJECT EMPTY';
		}
		if ( $panicOnEmpty ) {
			$flags[] = 'PANIC ON EMPTY';
		}
		if ( $limit > 0 ) {
			$flags[] = "LIMIT=$limit";
		}
		if ( $filter !== null ) {
			$flags[] = "FILTER=$filter";
		}

		Logger::info( sprintf(
			'Incoming=%s Ignored=%s Completed=%s',
			$incomingDir,
			$ignoredDir . ( $ignoredExists ? '' : ' (missing)' ),
			$completedDir . ( $completedExists ? '' : ' (missing)' )
		) );

		Logger::info( sprintf(
			'RemoteDir=%s Eligible=%d Skipped=%d%s',
			$remoteDir,
			count( $candidates ),
			$skipped,
			$flags ? ' [' . implode( ', ', $flags ) . ']' : ''
		) );

		$fetched = 0;
		$failed = 0;
		$deleted = 0;
		$emptyFailures = [];

		foreach ( $candidates as $entry ) {
			$file = $entry['name'];

			$remotePath = "$remoteDir/$file";
			$localPath = $incomingDir . DIRECTORY_SEPARATOR . $file;
			$tmpPath = $localPath . '.part';

			$remoteSize = null;
			$remoteMTime = null;

			if ( $verify ) {
				$remoteSize = $this->sftp->filesize( $remotePath );
				if ( $remoteSize === false || $remoteSize === null || $remoteSize < 0 ) {
					Logger::error( "Cannot determine remote size (skip): $remotePath" );
					$failed++;
					continue;
				}
			}

			if ( $deleteRemote ) {
				// Prefer the mtime we already have; fall back if missing.
				$remoteMTime = (int)$entry['mtime'];
				if ( $remoteMTime <= 0 ) {
					$remoteMTime = $this->sftp->filemtime( $remotePath );
				}
				if ( $remoteMTime === false || $remoteMTime === null || $remoteMTime <= 0 ) {
					Logger::error( "Cannot determine remote mtime (skip delete eligibility): $remotePath" );
					$remoteMTime = null;
				}
			}

			Logger::info( 'fetching ' . $file . ( $dryRun ? ' (dry-run)' : '' ) );

			if ( $dryRun ) {
				if ( $verify ) {
					Logger::info( "Would download $remotePath (remote size: $remoteSize) -> $localPath" );
				} else {
					Logger::info( "Would download $remotePath -> $localPath" );
				}

				if ( $deleteRemote ) {
					if ( $remoteMTime !== null ) {
						$eligible = ( $remoteMTime <= $deleteCutoff );
						Logger::info(
							"Would delete remote after verified download: $remotePath"
							. ' (mtime=' . gmdate( 'c', $remoteMTime ) . ', eligible=' . ( $eligible ? 'yes' : 'no' ) . ')'
						);
					} else {
						Logger::info( "Would delete remote after verified download: $remotePath (mtime unknown; would not delete)" );
					}
				}

				continue;
			}

			// Clean up any old partial.
			if ( file_exists( $tmpPath ) ) {
				if ( !unlink( $tmpPath ) ) {
					Logger::warning( "Failed to remove existing partial: $tmpPath" );
				}
			}

			// Download to .part first.
			$ok = $this->sftp->get( $remotePath, $tmpPath );
			if ( !$ok ) {
				Logger::error( "Failed to download: $remotePath" );
				if ( file_exists( $tmpPath ) ) {
					if ( !unlink( $tmpPath ) ) {
						Logger::warning( "Failed to remove partial after download failure: $tmpPath" );
					}
				}
				$failed++;
				continue;
			}

			// Verify download (size match) before rename/delete.
			if ( $verify ) {
				clearstatcache( true, $tmpPath );
				$localSize = filesize( $tmpPath );

				if ( $localSize === false ) {
					Logger::error( "Downloaded but cannot read local size for verification: $tmpPath" );
					if ( file_exists( $tmpPath ) ) {
						if ( !unlink( $tmpPath ) ) {
							Logger::warning( "Failed to remove partial after unreadable size: $tmpPath" );
						}
					}
					$failed++;
					continue;
				}

				if ( $localSize !== (int)$remoteSize ) {
					Logger::error(
						"Verification failed (size mismatch) for $file: remote=$remoteSize local=$localSize"
					);
					if ( file_exists( $tmpPath ) ) {
						if ( !unlink( $tmpPath ) ) {
							Logger::warning( "Failed to remove partial after size mismatch: $tmpPath" );
						}
					}
					$failed++;
					continue;
				}
			}

			// Reject empty downloads.
			clearstatcache( true, $tmpPath );
			$finalSize = filesize( $tmpPath );
			if ( $finalSize === false ) {
				Logger::error( "Downloaded but cannot read local size: $tmpPath" );
				if ( file_exists( $tmpPath ) ) {
					if ( !unlink( $tmpPath ) ) {
						Logger::warning( "Failed to remove partial after unreadable size: $tmpPath" );
					}
				}
				$failed++;
				continue;
			}

			if ( $rejectEmpty && $finalSize === 0 ) {
				if ( !unlink( $tmpPath ) ) {
					Logger::warning( "Downloaded file was empty but could not be removed: $tmpPath" );
				}
				$emptyFailures[] = $file;
				Logger::warning( "Downloaded file was empty; removed locally: $localPath" );
				continue;
			}

			// Finalize.
			if ( !rename( $tmpPath, $localPath ) ) {
				Logger::error( "Downloaded but failed to rename $tmpPath -> $localPath" );
				if ( file_exists( $tmpPath ) ) {
					if ( !unlink( $tmpPath ) ) {
						Logger::warning( "Failed to remove partial after rename failure: $tmpPath" );
					}
				}
				$failed++;
				continue;
			}

			$fetched++;

			// Optional remote delete ONLY after successful finalize + verify, and only if old enough.
			if ( $deleteRemote ) {
				if ( $remoteMTime === null ) {
					Logger::info( "Skipping remote delete (mtime unknown): $remotePath" );
					continue;
				}

				if ( $remoteMTime > $deleteCutoff ) {
					Logger::info(
						"Skipping remote delete (too new): $remotePath (mtime=" . gmdate( 'c', $remoteMTime ) . ')'
					);
					continue;
				}

				$delOk = $this->sftp->delete( $remotePath );
				if ( $delOk ) {
					Logger::info( "Deleted remote file: $remotePath" );
					$deleted++;
				} else {
					Logger::error( "Downloaded OK but failed to delete remote file: $remotePath" );
				}
			}
		}

		if ( $emptyFailures ) {
			Logger::error(
				'The following files were empty, please contact your provider: ' . implode( ', ', $emptyFailures )
			);

			if ( $panicOnEmpty ) {
				$this->error( 'One or more files were empty and panic-on-empty is enabled.' );
			}
		}

		Logger::info( sprintf(
			'Summary: fetched=%d failed=%d skipped=%d deleted=%d empty=%d%s',
			$fetched,
			$failed,
			$skipped,
			$deleted,
			count( $emptyFailures ),
			$dryRun ? ' (dry-run: no changes made)' : ''
		) );
	}

	/**
	 * Configure SSH algorithm preferences for best interoperability (phpseclib v3).
	 */
	private function configureSshAlgorithmDiscovery(): void {
		$this->sftp->setPreferredAlgorithms( [
			'kex' => [
				'curve25519-sha256',
				'curve25519-sha256@libssh.org',
				'diffie-hellman-group16-sha512',
				'diffie-hellman-group14-sha256',
				'diffie-hellman-group14-sha1',
			],
			'server_host_key' => [
				'rsa-sha2-512',
				'rsa-sha2-256',
				'ssh-ed25519',
				'ecdsa-sha2-nistp256',
				'ssh-rsa',
			],
			'encryption' => [
				'aes128-ctr',
				'aes192-ctr',
				'aes256-ctr',
				'aes128-gcm@openssh.com',
				'aes256-gcm@openssh.com',
			],
			'mac' => [
				'hmac-sha2-256-etm@openssh.com',
				'hmac-sha2-512-etm@openssh.com',
				'hmac-sha2-256',
				'hmac-sha2-512',
				'hmac-sha1',
			],
			'compression' => [
				'none',
				'zlib@openssh.com',
				'zlib',
			],
		] );
	}

	private function logNegotiatedAlgorithms(): void {
		// SFTP extends SSH2 in phpseclib v3, so call directly:
		$algorithms = $this->sftp->getAlgorithmsNegotiated();

		if ( !is_array( $algorithms ) || !$algorithms ) {
			Logger::info( 'Negotiated SSH algorithms: (unknown)' );
			return;
		}

		$parts = [];
		foreach ( [ 'kex', 'server_host_key', 'encryption', 'mac', 'compression' ] as $key ) {
			if ( isset( $algorithms[$key] ) && is_string( $algorithms[$key] ) && $algorithms[$key] !== '' ) {
				$parts[] = $key . '=' . $algorithms[$key];
			}
		}

		Logger::info( 'Negotiated SSH algorithms: ' . ( $parts ? implode( ' ', $parts ) : '(unknown)' ) );
	}

	/**
	 * List remote files with mtimes, so we can process most-recent-first.
	 *
	 * @param string $remoteDir
	 * @return array<int,array{name:string,mtime:int}>
	 */
	private function listRemoteFilesWithMtime( string $remoteDir ): array {
		$entries = [];

		$raw = $this->sftp->rawlist( $remoteDir );
		if ( is_array( $raw ) ) {
			foreach ( $raw as $name => $info ) {
				if ( $name === '.' || $name === '..' ) {
					continue;
				}

				$mtime = 0;
				if ( is_array( $info ) && isset( $info['mtime'] ) ) {
					$mtime = (int)$info['mtime'];
				}

				// If type is provided, 1 is file in phpseclib rawlist conventions.
				if ( is_array( $info ) && isset( $info['type'] ) ) {
					$type = (int)$info['type'];
					if ( $type !== 1 ) {
						continue;
					}
				}

				$entries[] = [
					'name' => (string)$name,
					'mtime' => $mtime,
				];
			}
			return $entries;
		}

		// Fallback: ls + per-file mtime (slower, but works if rawlist not permitted).
		$list = $this->sftp->nlist( $remoteDir );
		if ( $list === false ) {
			$this->error( "Failed to list remote directory: $remoteDir" );
		}

		foreach ( $list as $name ) {
			if ( $name === '.' || $name === '..' ) {
				continue;
			}
			$mtime = $this->sftp->filemtime( $remoteDir . '/' . $name );
			$entries[] = [
				'name' => (string)$name,
				'mtime' => ( $mtime === false || $mtime === null ) ? 0 : (int)$mtime,
			];
		}

		return $entries;
	}

	/**
	 * Load YAML config from either an explicit --config path or a --config-name.
	 *
	 * @return array<string,mixed>
	 */
	private function loadYamlConfig(): array {
		$configPath = $this->normalizePath( (string)$this->getOption( 'config' ) );
		$configName = trim( (string)$this->getOption( 'config-name' ) );

		if ( $configPath === '' && $configName === '' ) {
			return [];
		}

		if ( $configPath === '' ) {
			$configPath = $this->findConfigPathByName( $configName );
		}

		if ( !is_file( $configPath ) || !is_readable( $configPath ) ) {
			$this->error( "Config file not readable: $configPath" );
		}

		try {
			$parsed = Yaml::parseFile( $configPath );
		} catch ( ParseException $e ) {
			$this->error( "Failed to parse YAML config $configPath: " . $e->getMessage() );
			return [];
		}

		if ( !is_array( $parsed ) ) {
			$this->error( "Config file did not parse as a map: $configPath" );
		}

		Logger::info( "Loaded config: $configPath" );
		return $parsed;
	}

	/**
	 * Find a config file by name using standard fundraising locations.
	 *
	 * @param string $name
	 * @return string
	 */
	private function findConfigPathByName( string $name ): string {
		if ( $name === '' ) {
			$this->error( 'config-name must not be empty' );
		}

		$etc = "/etc/fundraising/$name.yaml";

		$home = getenv( 'HOME' );
		$homePath = $home ? rtrim( $home, DIRECTORY_SEPARATOR ) . "/.fundraising/$name.yaml" : '';

		if ( is_readable( $etc ) ) {
			return $etc;
		}
		if ( $homePath !== '' && is_readable( $homePath ) ) {
			return $homePath;
		}

		$msg = "Could not find readable config for $name. Looked for $etc";
		if ( $homePath !== '' ) {
			$msg .= " and $homePath";
		}
		$this->error( $msg );
		return '';
	}

	/**
	 * Get a value from a nested config array using dot notation.
	 *
	 * @param string $path
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	private function getFromConfig( string $path, mixed $default = null ): mixed {
		$current = $this->config;

		foreach ( explode( '.', $path ) as $key ) {
			if ( !is_array( $current ) || !array_key_exists( $key, $current ) ) {
				return $default;
			}
			$current = $current[$key];
		}

		return $current;
	}

	/**
	 * Use CLI option if present; otherwise fall back to config.
	 *
	 * @param string $optName
	 * @param string $configPath
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	private function chooseOptionOrConfig( string $optName, string $configPath, mixed $default = null ): mixed {
		$opt = $this->getOption( $optName );
		if ( $opt !== null && $opt !== '' ) {
			return $opt;
		}
		return $this->getFromConfig( $configPath, $default );
	}

	/**
	 * Resolve password authentication from either --password-file or config sftp.password.
	 *
	 * @return string|null
	 */
	private function readPasswordFromConfigOrFile(): ?string {
		$passwordFile = (string)$this->getOption( 'password-file' );
		if ( $passwordFile !== '' ) {
			return $this->readPasswordFile( $passwordFile );
		}

		$password = $this->getFromConfig( 'sftp.password' );
		if ( is_string( $password ) && trim( $password ) !== '' ) {
			return trim( $password );
		}

		return null;
	}

	/**
	 * Load a private key from --private-key-file (preferred) or config.
	 */
	private function loadPrivateKey(): ?PrivateKey {
		$path = trim( (string)$this->getOption( 'private-key-file' ) );
		$passwordOpt = $this->getOption( 'private-key-password' );
		$passwordOpt = is_string( $passwordOpt ) && trim( $passwordOpt ) !== '' ? trim( $passwordOpt ) : null;

		if ( $path !== '' ) {
			if ( !is_file( $path ) || !is_readable( $path ) ) {
				$this->error( "Private key file not readable: $path" );
			}
			$key = trim( (string)file_get_contents( $path ) );
			if ( $key === '' ) {
				$this->error( "Private key file is empty: $path" );
			}
			try {
				return $passwordOpt !== null
					? PublicKeyLoader::loadPrivateKey( $key, $passwordOpt )
					: PublicKeyLoader::loadPrivateKey( $key );
			} catch ( \Throwable $e ) {
				$this->error( "Failed to load private key from file $path: " . $e->getMessage() );
			}
		}

		return $this->loadPrivateKeyFromConfig();
	}

	/**
	 * Load an inline private key from config sftp.private_key.
	 * Optional passphrase via sftp.private_key_password.
	 */
	private function loadPrivateKeyFromConfig(): PrivateKey|null {
		$key = $this->getFromConfig( 'sftp.private_key' );
		if ( !is_string( $key ) || trim( $key ) === '' ) {
			return null;
		}

		$key = trim( $key );

		$password = $this->getFromConfig( 'sftp.private_key_password' );
		$password = is_string( $password ) && trim( $password ) !== '' ? trim( $password ) : null;

		try {
			return $password !== null
				? PublicKeyLoader::loadPrivateKey( $key, $password )
				: PublicKeyLoader::loadPrivateKey( $key );
		} catch ( \Throwable $e ) {
			$this->error( 'Failed to load private_key from config: ' . $e->getMessage() );
		}
		return null;
	}

	/**
	 * Verify server host key matches the pinned host key in config, if provided.
	 *
	 * Config value must match getServerPublicHostKey() exactly (including key type and base64).
	 */
	private function verifyPinnedHostKeyOrError( string $host ): void {
		$pinned = $this->getFromConfig( 'sftp.host_key' );
		if ( !is_string( $pinned ) || trim( $pinned ) === '' ) {
			return;
		}

		$pinned = trim( $pinned );

		$server = $this->sftp->getServerPublicHostKey();
		$serverStr = trim( (string)$server );

		if ( $server === false || $server === null || $serverStr === '' ) {
			$this->error( "Could not read server host key from $host" );
		}

		$pinnedB64 = $this->normalizeHostKey( $pinned );
		$serverB64 = $this->normalizeHostKey( $serverStr );

		if ( $pinnedB64 !== $serverB64 ) {
			Logger::error( "Pinned host key: $pinned" );
			Logger::error( "Server host key: $serverStr" );
			$this->error( "Host key mismatch for $host. Refusing to connect.", true );
		}

		Logger::info( "Host key verified for $host" );
	}

	private function normalizeHostKey( string $s ): string {
		$s = trim( $s );
		$parts = preg_split( '/\s+/', $s );
		if ( count( $parts ) < 2 ) {
			throw new \RuntimeException( "Invalid host key format: $s" );
		}
		return $parts[1]; // [algo, base64]
	}

	/**
	 * Convert CLI option values to boolean.
	 */
	private function asBool( string|bool|null|int $val ): bool {
		if ( is_bool( $val ) ) {
			return $val;
		}
		if ( $val === null ) {
			return false;
		}
		$stringValue = strtolower( trim( (string)$val ) );
		return in_array( $stringValue, [ '1', 'true', 'yes', 'y', 'on' ], true );
	}

	/**
	 * Normalize repeatable extra-path option values to a list of paths.
	 *
	 * @param mixed $extraPaths
	 * @return string[]
	 */
	private function normalizeExtraPaths( null|array|string $extraPaths ): array {
		if ( $extraPaths === null || $extraPaths === '' ) {
			return [];
		}

		$list = is_array( $extraPaths ) ? $extraPaths : [ $extraPaths ];

		$out = [];
		foreach ( $list as $p ) {
			$p = $this->normalizePath( (string)$p );
			if ( $p === '' ) {
				continue;
			}
			$out[] = $this->maybeRealpath( $p );
		}

		return $out;
	}

	/**
	 * Read a password from a file (single-line or trimmed content).
	 */
	private function readPasswordFile( string $path ): string {
		$path = $this->normalizePath( $path );

		if ( !is_file( $path ) || !is_readable( $path ) ) {
			$this->error( "Password file not readable: $path" );
		}

		$password = trim( (string)file_get_contents( $path ) );
		if ( $password === '' ) {
			$this->error( "Password file is empty: $path" );
		}

		return $password;
	}

	/**
	 * Parse a connect string in the form user@host.
	 *
	 * @return array{0:string,1:string} [ host, username ]
	 */
	private function parseConnectString( string $connect ): array {
		if ( !str_contains( $connect, '@' ) ) {
			$this->error( 'connect string must be user@host' );
		}

		[ $username, $host ] = explode( '@', $connect, 2 );
		$username = trim( $username );
		$host = trim( $host );

		if ( $username === '' || $host === '' ) {
			$this->error( "Invalid connect string (expected user@host): $connect" );
		}

		return [ $host, $username ];
	}

	/**
	 * Validate directory existence and access.
	 */
	private function validateDir( string $dir, bool $writable ): void {
		if ( !is_dir( $dir ) ) {
			$this->error( "Directory not found: $dir" );
		}
		if ( !is_readable( $dir ) ) {
			$this->error( "Directory not readable: $dir" );
		}
		if ( $writable && !is_writable( $dir ) ) {
			$this->error( "Directory not writable: $dir" );
		}
	}

	/**
	 * Normalize a path string (trim, normalize separators, remove trailing separators).
	 */
	private function normalizePath( string $path ): string {
		$path = trim( $path );
		if ( $path === '' ) {
			return $path;
		}

		if ( DIRECTORY_SEPARATOR === '/' ) {
			$path = str_replace( '\\', '/', $path );
		} else {
			$path = str_replace( '/', '\\', $path );
		}

		return rtrim( $path, DIRECTORY_SEPARATOR );
	}

	/**
	 * Canonicalize a path if it exists; otherwise return it unchanged.
	 */
	private function maybeRealpath( string $path ): string {
		$real = realpath( $path );
		return $real !== false ? $real : $path;
	}

	/**
	 * Infer a sibling directory (ignored/completed) based on incoming directory.
	 *
	 * Requires that incoming-directory ends with 'incoming' for safety.
	 */
	private function inferSiblingDir( string $incomingDir, string $siblingName ): string {
		$incomingDir = $this->normalizePath( $incomingDir );
		$incomingDir = $this->maybeRealpath( $incomingDir );

		$leaf = basename( $incomingDir );
		if ( $leaf !== 'incoming' ) {
			$this->error(
				"incoming-directory must end with 'incoming' to infer sibling directories; got: $incomingDir"
			);
		}

		$parent = dirname( $incomingDir );
		return rtrim( $parent, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $siblingName;
	}

	/**
	 * Recursively collect basenames from given directories.
	 *
	 * @param string[] $dirs
	 * @return bool[] Map of basename => true
	 */
	private function collectBasenames( array $dirs ): array {
		$set = [];

		foreach ( $dirs as $dir ) {
			$it = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $it as $file ) {
				/** @var \SplFileInfo $file */
				if ( $file->isFile() ) {
					$set[$file->getBasename()] = true;
				}
			}
		}

		return $set;
	}

	/**
	 * Safety check: accept only plain basenames (no slashes, no traversal, no NUL).
	 */
	private function isSafeBasename( string $name ): bool {
		if ( $name === '' || $name === '.' || $name === '..' ) {
			return false;
		}

		if ( str_contains( $name, '/' ) || str_contains( $name, '\\' ) ) {
			return false;
		}

		if ( str_contains( $name, "\0" ) ) {
			return false;
		}

		if ( str_contains( $name, '..' ) ) {
			return false;
		}

		return true;
	}
}

$maintClass = SFTPDownload::class;
require RUN_MAINTENANCE_IF_MAIN;
