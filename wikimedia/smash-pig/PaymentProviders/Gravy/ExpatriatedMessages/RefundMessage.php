<?php

namespace SmashPig\PaymentProviders\Gravy\ExpatriatedMessages;

class RefundMessage extends GravyMessage {

	// @var string The refund id from Gravy
	private $gateway_refund_id;

	// @var string The parent id from Gravy
	private $gateway_parent_id;

	private $action = "RefundAction";

	public function init( array $notification ): GravyMessage {
		$this->setGatewayRefundId( $notification["id"] );
		$this->setGatewayParentId( $notification["gateway_parent_id"] );
		$this->setMessageDate( $notification["created_at"] );
		return $this;
	}

	public function validate(): bool {
		return true;
	}

	public function getDestinationQueue(): ?string {
		return 'refund';
	}

	public function getGatewayRefundId(): string {
		return $this->gateway_refund_id;
	}

	public function setGatewayRefundId( string $gateway_refund_id ): void {
		$this->gateway_refund_id = $gateway_refund_id;
	}

	public function getGatewayParentId(): string {
		return $this->gateway_parent_id;
	}

	public function setGatewayParentId( string $gateway_parent_id ): void {
		$this->gateway_parent_id = $gateway_parent_id;
	}

	public function getAction(): ?string {
		return $this->action;
	}
}
