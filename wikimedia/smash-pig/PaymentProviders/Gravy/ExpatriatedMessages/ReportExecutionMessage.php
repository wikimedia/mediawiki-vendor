<?php

namespace SmashPig\PaymentProviders\Gravy\ExpatriatedMessages;

class ReportExecutionMessage extends GravyMessage {

	/** @var string The Report Execution id from Gravy */
	private string $report_execution_id;
	private string $action = 'ReportExecutionAction';

	public function init( array $notification ): GravyMessage {
		$this->setReportExecutionId( $notification['id'] );
		$this->setMessageDate( $notification['created_at'] );
		return $this;
	}

	public function validate(): bool {
		return true;
	}

	public function getDestinationQueue(): ?string {
		return 'jobs-gravy';
	}

	public function getReportExecutionId(): string {
		return $this->report_execution_id;
	}

	public function setReportExecutionId( string $report_execution_id ): void {
		$this->report_execution_id = $report_execution_id;
	}

	public function getAction(): ?string {
		return $this->action;
	}
}
