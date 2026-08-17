<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;

/**
 * Fetch recurring payment tokens from Gravy with optional filtering by status.
 * Results are paginated automatically.
 *
 * Usage examples:
 *   php FetchRecurringPaymentTokens.php
 *   php FetchRecurringPaymentTokens.php --status stored
 *   php FetchRecurringPaymentTokens.php --status processing --limit 50
 *   php FetchRecurringPaymentTokens.php --page 3
 */
class FetchRecurringPaymentTokens extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'gravy';
		$this->addOption( 'status', 'Filter by payment method status (e.g. failed, processing)', '', 's' );
		$this->addOption( 'limit', 'Number of results per page (default: 100)', 100, 'l' );
		$this->addOption( 'page', 'Specify page of result (default: 1)', 1, 'p' );
	}

	public function execute(): void {
		$params = [
			'limit' => (int)$this->getOption( 'limit' ),
		];

		$status = $this->getOption( 'status' );
		if ( $status ) {
			$params['status'] = $status;
		}

		$providerConfiguration = Context::get()->getProviderConfiguration();
		$api = $providerConfiguration->object( 'api' );

		$targetPage = (int)$this->getOption( 'page' );
		$totalFetched = 0;
		$page = 1;

		do {
			Logger::info( "Fetching page $page of recurring payment tokens" );
			$response = $api->listPaymentMethods( $params );

			if ( isset( $response['type'] ) && $response['type'] === 'error' ) {
				Logger::error( 'Error fetching payment methods: ' . ( $response['message'] ?? 'Unknown error' ) );
				return;
			}

			$items = $response['items'] ?? [];
			$count = count( $items );

			if ( $page === $targetPage ) {
				$totalFetched += $count;
				foreach ( $items as $item ) {
					echo json_encode( $item ) . "\n";
				}
				Logger::info( "Fetched $count tokens on page $page" );
				break;
			}

			$nextCursor = $response['next_cursor'] ?? null;
			if ( $nextCursor ) {
				$params['cursor'] = $nextCursor;
			}
			$page++;
		} while ( $nextCursor );

		Logger::info( "Done. Total recurring payment tokens fetched: $totalFetched" );
	}
}

$maintClass = FetchRecurringPaymentTokens::class;

require RUN_MAINTENANCE_IF_MAIN;
