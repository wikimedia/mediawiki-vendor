<?php
namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Gravy\Responses\ReportResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyReportResponseFactory extends GravyPaymentResponseFactory {
	protected static function createBasicResponse(): ReportResponse {
		return new ReportResponse();
	}

	/**
	 * @param PaymentProviderResponse $reportResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $reportResponse, array $normalizedResponse ): void {
		if ( !$reportResponse instanceof ReportResponse ) {
			return;
		}
		self::setReportUrl( $reportResponse, $normalizedResponse );
		self::setReportId( $reportResponse, $normalizedResponse );
		self::setReportExecutionId( $reportResponse, $normalizedResponse );
		self::setReportName( $reportResponse, $normalizedResponse );
		self::setReportCreatedBy( $reportResponse, $normalizedResponse );
	}

	protected static function setReportUrl( ReportResponse $reportResponse, array $normalizedResponse ): void {
		if ( isset( $normalizedResponse['report_url'] ) ) {
			$reportResponse->setReportUrl( $normalizedResponse['report_url'] );
		}
	}

	protected static function setReportId( ReportResponse $reportResponse, array $normalizedResponse ): void {
		if ( isset( $normalizedResponse['report_id'] ) ) {
			$reportResponse->setReportId( $normalizedResponse['report_id'] );
		}
	}

	protected static function setReportExecutionId( ReportResponse $reportResponse, array $normalizedResponse ): void {
		if ( isset( $normalizedResponse['report_execution_id'] ) ) {
			$reportResponse->setReportExecutionId( $normalizedResponse['report_execution_id'] );
		}
	}

	protected static function setReportName( ReportResponse $reportResponse, array $normalizedResponse ): void {
		if ( isset( $normalizedResponse['report_name'] ) ) {
			$reportResponse->setReportName( $normalizedResponse['report_name'] );
		}
	}

	protected static function setReportCreatedBy( ReportResponse $reportResponse, array $normalizedResponse ): void {
		if ( isset( $normalizedResponse['report_created_by'] ) ) {
			$reportResponse->setReportCreatedBy( $normalizedResponse['report_created_by'] );
		}
	}

}
