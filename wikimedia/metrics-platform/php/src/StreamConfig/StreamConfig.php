<?php

namespace Wikimedia\MetricsPlatform\StreamConfig;

class StreamConfig {

	/**
	 * @var string[] The context attributes that the Metrics Platform Client can add to an event
	 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/Contextual_attributes
	 */
	public const CONTEXTUAL_ATTRIBUTES = [
		'agent_client_platform',
		'agent_client_platform_family',
		'agent_ua_string',

		'page_id',
		'page_title',
		'page_namespace_id',
		'page_namespace_name',
		'page_revision_id',
		'page_wikidata_id',
		'page_wikidata_qid',
		'page_content_language',
		'page_is_redirect',
		'page_user_groups_allowed_to_move',
		'page_user_groups_allowed_to_edit',

		'mediawiki_skin',
		'mediawiki_version',
		'mediawiki_is_production',
		'mediawiki_is_debug_mode',
		'mediawiki_database',
		'mediawiki_site_content_language',
		'mediawiki_site_content_language_variant',

		'performer_is_logged_in',
		'performer_id',
		'performer_name',
		'performer_session_id',
		'performer_pageview_id',
		'performer_groups',
		'performer_is_bot',
		'performer_is_temp',
		'performer_language',
		'performer_language_variant',
		'performer_can_probably_edit_page',
		'performer_edit_count',
		'performer_edit_count_bucket',
		'performer_registration_dt',
	];

	/**
	 * @param array $streamConfig
	 */
	public function __construct(
		private readonly array $streamConfig
	) {
	}

	/**
	 * Gets the context attributes that should be mixed into an event before submission.
	 *
	 * @see StreamConfig::CONTEXTUAL_ATTRIBUTES
	 *
	 * @return string[]
	 */
	public function getRequestedValues(): array {
		return $this->streamConfig['producers']['metrics_platform_client']['provide_values'] ?? [];
	}

	/**
	 * Gets the curation rules that should be applied to an event before submission.
	 */
	public function getCurationRules(): array {
		return $this->streamConfig['producers']['metrics_platform_client']['curation'] ?? [];
	}
}
