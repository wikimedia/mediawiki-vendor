<?php

namespace SmashPig\PaymentProviders\Gravy\Responses;

use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class ReportResponse extends PaymentProviderResponse {

	protected $report_created_by;

	protected $report_execution_id;

	protected $report_id;

	protected $report_name;

	protected $report_url;

	public function setReportCreatedBy( string $report_created_by ): self {
		$this->report_created_by = $report_created_by;
		return $this;
	}

	public function setReportExecutionId( string $report_execution_id ): self {
		$this->report_execution_id = $report_execution_id;
		return $this;
	}

	public function setReportId( string $report_id ): self {
		$this->report_id = $report_id;
		return $this;
	}

	public function setReportName( string $report_name ): self {
		$this->report_name = $report_name;
		return $this;
	}

	public function setReportUrl( string $report_url ): self {
		$this->report_url = $report_url;
		return $this;
	}

	public function getReportCreatedBy(): string {
		return $this->report_created_by;
	}

	public function getReportExecutionId(): string {
		return $this->report_execution_id;
	}

	public function getReportId(): string {
		return $this->report_id;
	}

	public function getReportName(): string {
		return $this->report_name;
	}

	public function getReportUrl(): string {
		return $this->report_url;
	}
}
