<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Maintenance\MaintenanceBase;

/**
 * Command-line script to download new audit reports via MWS
 */
class DownloadReports extends MaintenanceBase {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'days', 'Number of days of reports to download', 7, 'd' );
		$this->addOption( 'archive-path', 'Directory to scan for archived reports' );
		$this->addOption( 'download-path', 'Directory to save downloaded reports' );
		// Override the default config node
		$this->desiredOptions['config-node']['default'] = 'amazon';
	}

	public function execute() {
		$downloaderConfig = [
			'archive-path' => $this->getOption( 'archive-path' ),
			'download-path' => $this->getOption( 'download-path' ),
			'days' => $this->getOption( 'days' ),
		];
		$downloader = new ReportDownloader( $downloaderConfig );
		$downloader->download();
	}
}

$maintClass = DownloadReports::class;

require RUN_MAINTENANCE_IF_MAIN;
