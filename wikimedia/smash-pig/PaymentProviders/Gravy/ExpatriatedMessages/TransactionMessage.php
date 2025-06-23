<?php

namespace SmashPig\PaymentProviders\Gravy\ExpatriatedMessages;

class TransactionMessage extends GravyMessage {

	/** @var string The gateway_txn_id from Gravy */
	private string $gateway_txn_id;

	/** @var string The transaction's payment method from Gravy */
	private string $payment_method;

	/** @var array The transaction details from Gravy */
	private array $transaction_details = [];

	private string $action = "TransactionAction";

	public function init( array $notification ): GravyMessage {
		$this->setTransactionId( $notification['id'] );
		$this->setMessageDate( $notification['created_at'] );
		$this->setTransactionPaymentMethod( $notification['target']['payment_method']['method'] );
		$this->setTransactionDetails( $notification['target'] );
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

	public function getTransactionPaymentMethod(): string {
		return $this->payment_method;
	}

	public function setTransactionPaymentMethod( string $payment_method ): void {
		$this->payment_method = $payment_method;
	}

	public function getTransactionDetails(): array {
		return $this->transaction_details;
	}

	public function setTransactionDetails( array $transaction_details ): void {
		$this->transaction_details = $transaction_details;
	}

	public function setTransactionId( string $gateway_txn_id ): void {
		$this->gateway_txn_id = $gateway_txn_id;
	}

	public function getAction(): ?string {
		return $this->action;
	}
}
