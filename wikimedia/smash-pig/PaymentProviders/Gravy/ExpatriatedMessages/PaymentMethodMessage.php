<?php

namespace SmashPig\PaymentProviders\Gravy\ExpatriatedMessages;

class PaymentMethodMessage extends GravyMessage {
	private string $method;
	private string $eventName;
	private array $payment_method_details = [];
	private ?string $action;

	public function init( array $notification ): GravyMessage {
		// We set the event name first so we can make decisions based off of it.
		$this->setEventName( $notification['raw_response']['name'] );
		$this->setPaymentMethod( $notification['target']['method'] );
		$this->setMessageDate( $notification['created_at'] );
		$this->setPaymentMethodDetails( $notification['target'] );
		$this->setAction();
		return $this;
	}

	public function validate(): bool {
		return !empty( $this->eventName );
	}

	public function getDestinationQueue(): ?string {
		// Route deletion events to jobs queue for processing
		if ( $this->isPaymentMethodDeletedEvent() ) {
			return 'jobs-gravy';
		}

		// For now, ignore other payment method events
		return null;
	}

	public function getAction(): ?string {
		return $this->action;
	}

	public function getPaymentMethod(): string {
		return $this->method;
	}

	public function getPaymentMethodId(): string {
		return $this->payment_method_details['id'] ?? '';
	}

	public function getPaymentMethodDetails(): array {
		return $this->payment_method_details;
	}

	public function getEventName(): string {
		return $this->eventName;
	}

	public function getPaymentMethodUpdateDate(): string {
		return $this->payment_method_details['updated_at'];
	}

	protected function setEventName( string $name ): void {
		$this->eventName = $name;
	}

	public function setAction(): void {
		if ( $this->isPaymentMethodDeletedEvent() ) {
			$this->action = 'PaymentMethodDeleteAction';
		} else {
			$this->action = null;
		}
	}

	protected function setPaymentMethod( string $method ): void {
		$this->method = $method;
	}

	protected function setPaymentMethodDetails( array $payment_method_details ): void {
		$this->payment_method_details = $payment_method_details;
	}

	/**
	 * @return bool
	 */
	protected function isPaymentMethodDeletedEvent(): bool {
		return $this->eventName === 'payment-method.deleted';
	}
}
