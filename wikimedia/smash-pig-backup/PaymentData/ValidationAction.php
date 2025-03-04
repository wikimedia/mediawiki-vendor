<?php
namespace SmashPig\PaymentData;

/**
 * Allowed values for the action taken after validating
 */
class ValidationAction {
	// Actions to take after evaluating fraudiness
	const CHALLENGE = 'challenge'; // likely fraud? We haven't been using this
	const PROCESS = 'process'; // all clear to process payment
	const REJECT = 'reject'; // very likely fraud - cancel the payment
	const REVIEW = 'review'; // potential fraud - leave for Donor Services
}
