<?php

namespace Wikimedia\MetricsPlatform;

/**
 * InteractionData trait
 *
 * The Metrics Platform core interactions API includes submit methods that allow
 * client code to pass in an InteractionData data array. While only the "action"
 * property is required, optional properties can be included with event submission.
 * This trait is used by the Metrics Client to validate the allowed keys of the
 * InteractionData parameter.
 *
 * See @ToDo add the link to the MP core interactions schema when merged.
 */
trait InteractionDataTrait {
	/** @var array */
	private array $allowlist = [
		'action_subtype',
		'action_source',
		'action_context',
		'element_id',
		'element_friendly_name',
		'funnel_name',
		'funnel_entry_token',
		'funnel_event_sequence_position'
	];

	/**
	 *  Get formatted InteractionData.
	 *
	 * @param string $action
	 * @param array $data
	 *
	 */
	public function getInteractionData( string $action, array $data ): array {
		return array_merge(
			[ 'action' => $action ],
			array_intersect_key(
				$data,
				array_flip( $this->allowlist )
			)
		);
	}
}
