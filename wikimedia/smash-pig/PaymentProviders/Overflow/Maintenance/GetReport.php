<?php

namespace SmashPig\PaymentProviders\Overflow\Maintenance;

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Overflow\Audit\Api;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

class GetReport extends MaintenanceBase {

	private Api $api;

	/**
	 * @throws \SmashPig\Core\SmashPigException
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption(
			'start-date',
			'Filter deposits by startDate; accepts any strtotime()-parseable date/time',
			'yesterday'
		);
		$this->addOption(
			'end-date',
			'Filter deposits by endDate; accepts any strtotime()-parseable date/time'
		);
		$this->addOption(
			'limit',
			'Optional maximum results per deposits list call',
			'',
			'l'
		);
		$this->addOption(
			'max-pages',
			'Optional maximum pages to fetch for list calls',
			'',
			'm'
		);
		$this->addOption(
			'gateway-account',
			'Gateway account name (e.g. Foundation, Endowment). Used for API credential lookup and output filenames.',
			'',
			'g'
		);
		$this->addOption(
			'path',
			'Optional output directory; overrides reports_incoming_path config'
		);

		$this->desiredOptions['config-node']['default'] = 'overflow';
	}

	public function execute(): void {
		$path = $this->getIncomingPath();

		if ( !is_dir( $path ) ) {
			throw new \RuntimeException(
				'Output directory does not exist: ' . $path
			);
		}

		$this->api = new Api( [ 'gateway_account' => $this->getGatewayAccount() ] );

		$this->runDeposits();
	}

	/**
	 * @return string
	 */
	private function getGatewayAccount(): string {
		$gatewayAccount = trim( (string)$this->getOption( 'gateway-account' ) );
		if ( $gatewayAccount === '' ) {
			throw new \InvalidArgumentException( '--gateway-account is required.' );
		}
		return $gatewayAccount;
	}

	private function runDeposits(): void {
		$result = $this->collectDeposits();

		$retrieved = 0;
		$skipped = 0;
		$retrievalFailures = 0;

		foreach ( $result['results'] as $deposit ) {
			if (
				!is_array( $deposit ) ||
				!isset( $deposit['id'] )
			) {
				continue;
			}

			$depositId = $deposit['id'];
			$filename = $this->getDepositFilename( $depositId );

			/*
			 * Once we have an artifact in any report lifecycle
			 * directory, don't retrieve it again.
			 */
			if ( $this->auditFileExists( $filename ) ) {
				$skipped++;
				Logger::info( 'Skipping existing Overflow deposit: ' . $depositId );
				continue;
			}

			try {
				$data = $this->api->getDepositWithContributions( $depositId );
				$data['gateway_account'] = $this->getGatewayAccount();
			} catch ( \Throwable $e ) {
				$retrievalFailures++;

				Logger::error(
					sprintf(
						'Unable to retrieve Overflow deposit %s: %s',
						$depositId,
						$e->getMessage()
					)
				);

				/*
				 * No file was written, so the next run will retry it.
				 */
				continue;
			}

			/*
			 * Write the complete response before validation.
			 *
			 * This is deliberate: a failed validation should leave
			 * us with the exact API response needed for investigation
			 * and parser tests.
			 */
			try {
				$this->writeDepositJson(
					$this->getIncomingPath(),
					$data
				);

				$retrieved++;
			} catch ( \Throwable $e ) {
				Logger::error(
					sprintf(
						'Unable to save Overflow deposit %s: %s',
						$depositId,
						$e->getMessage()
					)
				);

				/*
				 * If writing failed, don't pretend that we have
				 * successfully captured the artifact.
				 */
				continue;
			}
		}

		Logger::info(
			sprintf(
				'Overflow deposits: retrieved=%d, skipped=%d, retrieval failures=%d',
				$retrieved,
				$skipped,
				$retrievalFailures
			)
		);
	}

	/**
	 * Collect paginated Overflow deposits.
	 *
	 * @return array{results: array, next_pages: int}
	 */
	private function collectDeposits(): array {
		$results = [];
		$page = 1;
		$maxPages = $this->getPositiveIntOption( 'max-pages' );

		while ( true ) {
			if ( $maxPages !== null && $page > $maxPages ) {
				break;
			}

			$response = $this->fetchDepositsPage( $page );
			$pageResults = $response['data'] ?? [];

			if ( !is_array( $pageResults ) || $pageResults === [] ) {
				break;
			}

			$results = array_merge(
				$results,
				$pageResults
			);

			$totalCount = (int)(
				$response['totalCount'] ?? 0
			);

			$limit = $this->getPositiveIntOption( 'limit' );

			/*
			 * If the API tells us the total number of results,
			 * use that to decide whether another page is needed.
			 *
			 * When a limit was supplied, also stop if the returned
			 * page is shorter than the requested limit.
			 */
			if ( $totalCount === 0 ) {
				break;
			}

			if ( count( $results ) >= $totalCount ) {
				break;
			}

			if (
				$limit !== null &&
				count( $pageResults ) < $limit
			) {
				break;
			}

			$page++;
		}

		return [
			'results' => $results,
			'next_pages' => $page,
		];
	}

	/**
	 * Fetch one page of deposits.
	 *
	 * @param int $page
	 *
	 * @return array
	 */
	private function fetchDepositsPage( int $page ): array {
		$params = [
			'page' => $page,
		];

		$limit = $this->getPositiveIntOption( 'limit' );

		if ( $limit !== null ) {
			$params['limit'] = $limit;
		}

		$startDate = $this->getNormalizedDateOption( 'start-date' );
		$endDate = $this->getNormalizedDateOption( 'end-date' );

		if ( $startDate !== null ) {
			$params['startDate'] = $startDate;
		}
		if ( $endDate !== null ) {
			$params['endDate'] = $endDate;
		}

		return $this->api->getDeposits( $params );
	}

	/**
	 * Check whether an audit artifact already exists in any
	 * of the report lifecycle directories.
	 *
	 * @param string $filename
	 *
	 * @return bool
	 */
	private function auditFileExists( string $filename ): bool {
		foreach ( $this->getReportPaths() as $path ) {
			if (
				file_exists( $path . '/' . $filename ) ||
				file_exists( $path . '/' . $filename . '.gz' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the report lifecycle paths.
	 *
	 * @return array
	 */
	private function getReportPaths(): array {
		$incoming = $this->getIncomingPath();

		return [
			$incoming,
			str_replace( 'incoming', 'completed', $incoming ),
			str_replace( 'incoming', 'ignored', $incoming ),
		];
	}

	/**
	 * Emit a JSON file to disk.
	 *
	 * @param string $path
	 * @param string $filename
	 * @param array $payload
	 *
	 * @return void
	 */
	private function emitJsonFile(
		string $path,
		string $filename,
		array $payload
	): void {
		$json = json_encode(
			$payload,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		if ( $json === false ) {
			throw new \RuntimeException(
				'Unable to encode JSON payload'
			);
		}

		$fullPath = $path . '/' . $filename;

		$result = file_put_contents(
			$fullPath,
			$json . PHP_EOL
		);

		if ( $result === false ) {
			throw new \RuntimeException(
				'Unable to write JSON file: ' . $fullPath
			);
		}

		Logger::info(
			'Saved Overflow JSON file to ' . $fullPath
		);
	}

	/**
	 * Write the combined deposit/contribution/summary JSON payload.
	 *
	 * @param string $path
	 * @param array $data
	 *
	 * @return void
	 */
	private function writeDepositJson(
		string $path,
		array $data
	): void {
		$depositId = $data['deposit']['id'];

		$this->emitJsonFile(
			$path,
			$this->getDepositFilename( $depositId ),
			$data
		);
	}

	/**
	 * @param string $depositId
	 *
	 * @return string
	 */
	private function getDepositFilename( string $depositId ): string {
		return 'overflow-' . $this->sanitizeForFilename( $this->getGatewayAccount() ) . '-' . $depositId . '.json';
	}

	private function sanitizeForFilename( string $value ): string {
		return preg_replace( '/[^A-Za-z0-9_-]+/', '-', $value );
	}

}

$maintClass = \SmashPig\PaymentProviders\Overflow\Maintenance\GetReport::class;
require RUN_MAINTENANCE_IF_MAIN;
