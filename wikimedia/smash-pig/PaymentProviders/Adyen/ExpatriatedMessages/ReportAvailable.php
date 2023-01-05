<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Adyen\Jobs\DownloadReportJob;

class ReportAvailable extends AdyenMessage {
	/**
	 * Will run all the actions that are loaded (from the 'actions' configuration
	 * node) and that are applicable to this message type. Will return true
	 * if all actions returned true. Otherwise will return false. This implicitly
	 * means that the message will be re-queued if any action fails. Therefore
	 * all actions need to be idempotent.
	 *
	 * @return bool True if all actions were successful. False otherwise.
	 */
	public function runActionChain() {
		Logger::info(
			"Received new report from Adyen: {$this->pspReference}. Generated: {$this->eventDate}.",
			$this->reason
		);

		// The original audit file used was the settlement_detail_report which was available once a week
		// The payments_accounting_report is a nightly file that has the same information
		if ( strpos( $this->pspReference, 'settlement_detail_report' ) === 0 ||
			 strpos( $this->pspReference, 'payments_accounting_report' ) === 0 ) {
			$jobObject = DownloadReportJob::factory(
				$this->merchantAccountCode,
				$this->reason
			);
			QueueWrapper::push( 'jobs-adyen', $jobObject );
		} else {
			// We don't know how to handle this report yet
			Logger::notice( "Do not know how to handle report with name '{$this->pspReference}'" );
		}

		return parent::runActionChain();
	}
}
