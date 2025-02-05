<?php

namespace SmashPig\PaymentProviders\Gravy\ExpatriatedMessages;

class TransactionMessage extends GravyMessage {

	/** @var string The gateway_txn_id from Gravy */
	private string $gateway_txn_id;

	private string $action = "TransactionAction";

	public function init( array $notification ): GravyMessage {
		$this->setTransactionId( $notification["id"] );
		$this->setMessageDate( $notification["created_at"] );
		return $this;
	}

	public function validate(): bool {
		return true;
	}

	public function getDestinationQueue(): ?string {
		return 'jobs-gravy';
	}

	public function getTransactionId(): string {
		return $this->gateway_txn_id;
	}

	public function setTransactionId( string $gateway_txn_id ): void {
		$this->gateway_txn_id = $gateway_txn_id;
	}

	public function getAction(): ?string {
		return $this->action;
	}
}
