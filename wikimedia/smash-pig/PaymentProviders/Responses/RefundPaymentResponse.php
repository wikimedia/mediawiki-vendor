<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class RefundPaymentResponse
 * @package SmashPig\PaymentProviders
 */
class RefundPaymentResponse extends PaymentDetailResponse {
	/**
	 * Payment provider refund ID
	 *
	 * https://www.mediawiki.org/wiki/Fundraising_tech/Transaction_IDs
	 *
	 * @var string|null
	 */
	protected $gateway_refund_id;

	 /**
	  * Payment provider refunded transaction ID
	  *
	  * https://www.mediawiki.org/wiki/Fundraising_tech/Transaction_IDs
	  *
	  * @var string|null
	  */
	protected $gateway_parent_id;

	/**
	 * @var string|null
	 */
	protected $gateway;

	/**
	 * @var string|null
	 */
	protected $reason;

	/**
	 * @return string
	 */
	public function getGatewayRefundId(): ?string {
		return $this->gateway_refund_id;
	}

	/**
	 * @param string $refund_id
	 * @return static
	 */
	public function setGatewayRefundId( string $refund_id ): self {
		$this->gateway_refund_id = $refund_id;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getGatewayParentId(): ?string {
		return $this->gateway_parent_id;
	}

	/**
	 * @param string $parent_id
	 * @return static
	 */
	public function setGatewayParentId( string $parent_id ): self {
		$this->gateway_parent_id = $parent_id;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getGateway(): ?string {
		return $this->gateway;
	}

	/**
	 * @param string $gateway
	 * @return static
	 */
	public function setGateway( string $gateway ): self {
		$this->gateway = $gateway;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getReason(): ?string {
		return $this->reason;
	}

	/**
	 * @param string $reason
	 * @return static
	 */
	public function setReason( string $reason ): self {
		$this->reason = $reason;
		return $this;
	}
}
