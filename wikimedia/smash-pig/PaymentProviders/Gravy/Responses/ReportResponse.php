<?php

namespace SmashPig\PaymentProviders\Gravy\Responses;

use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class ReportResponse extends PaymentProviderResponse {

	protected $report_execution_id;

	protected $report_id;

	protected $report_url;

	public function setReportExecutionId( string $report_execution_id ) {
		$this->report_execution_id = $report_execution_id;
		return $this;
	}

	public function setReportId( string $report_id ) {
		$this->report_id = $report_id;
		return $this;
	}

	public function setReportUrl( string $report_url ) {
		$this->report_url = $report_url;
		return $this;
	}

	public function getReportUrl(): string {
		return $this->report_url;
	}

	public function getReportId(): string {
		return $this->report_id;
	}

	public function getReportExecutionId(): string {
		return $this->report_execution_id;
	}
}
