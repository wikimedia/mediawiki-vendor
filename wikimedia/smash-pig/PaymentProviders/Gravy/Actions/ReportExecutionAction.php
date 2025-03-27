<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\ReportExecutionMessage;
use SmashPig\PaymentProviders\Gravy\Jobs\DownloadReportJob;
use SmashPig\PaymentProviders\Gravy\Responses\ReportResponse;

class ReportExecutionAction extends GravyAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'TransactionAction' );
		$reportExecutionDetails = $this->getReportExecutionDetails( $msg );

		if ( $reportExecutionDetails->isSuccessful() ) {
			$tl->info(
				"Report execution details for execution id {$reportExecutionDetails->getReportId()}: " . json_encode( $reportExecutionDetails->getRawResponse() )
			);
			$reportUrl = $this->generateReportDownloadUrl( $reportExecutionDetails );

			if ( $reportUrl->isSuccessful() ) {
				$message = $reportUrl->getNormalizedResponse();
				unset( $message['raw_response'] );
				$message = DownloadReportJob::factory( $message );
				QueueWrapper::push( $msg->getDestinationQueue(), $message );
			} else {
				$tl->info(
					"Problem generating report with id {$reportExecutionDetails->getReportId()}"
				);
			}

		} else {
			$tl->info(
				"Problem locating report execution with id {$msg->getReportExecutionId()}"
			);
		}

		return true;
	}

	public function getReportExecutionDetails( ReportExecutionMessage $msg ): ReportResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$reportExecutionDetails = $provider->getReportExecutionDetails( [
			'report_execution_id' => $msg->getReportExecutionId()
		] );

		return $reportExecutionDetails;
	}

	public function generateReportDownloadUrl( ReportResponse $response ): ReportResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$reportDetails = $provider->generateReportDownloadUrl( [
			'report_execution_id' => $response->getReportExecutionId(),
			'report_id' => $response->getReportId()
		] );

		return $reportDetails;
	}
}
