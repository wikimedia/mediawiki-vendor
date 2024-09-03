<?php
namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class GravyRefundResponseFactory extends GravyPaymentResponseFactory {
	protected static function createBasicResponse(): RefundPaymentResponse {
		return new RefundPaymentResponse();
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $normalizedResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		if ( !$paymentResponse instanceof RefundPaymentResponse ) {
			return;
		}
		self::setRefundReason( $paymentResponse, $normalizedResponse );
		self::setRefundAmount( $paymentResponse, $normalizedResponse );
		self::setRefundCurrency( $paymentResponse, $normalizedResponse );
		self::setRefundId( $paymentResponse, $normalizedResponse );
		self::setParentId( $paymentResponse, $normalizedResponse );
	}

	protected static function setRefundReason( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setReason( $normalizedResponse['reason'] );
	}

	protected static function setRefundAmount( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setAmount( $normalizedResponse['amount'] );
	}

	protected static function setRefundCurrency( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setCurrency( $normalizedResponse['currency'] );
	}

	protected static function setRefundId( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setGatewayRefundId( $normalizedResponse['gateway_refund_id'] );
	}

	protected static function setParentId( RefundPaymentResponse $refundResponse, array $normalizedResponse ): void {
		$refundResponse->setGatewayParentId( $normalizedResponse['gateway_parent_id'] );
	}

}
