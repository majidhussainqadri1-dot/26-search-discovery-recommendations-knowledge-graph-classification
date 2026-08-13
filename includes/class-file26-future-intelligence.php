<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Future Search & Knowledge Intelligence Superset 24 orchestration owner. */
final class Future_Intelligence {
	const REST_NAMESPACE = 'sabri-search/v1';
	const CONTRACT = 'sabri.file26.future.v1.3';
	const META_TRAILS = 'sabri_file26_research_trails_v1';
	const META_ALERTS = 'sabri_file26_saved_search_alerts_v1';
	const META_HISTORY = 'sabri_file26_search_history_sync_v1';
	const META_HISTORY_OPT_IN = 'sabri_file26_search_history_sync_opt_in_v1';
	const META_DISCOVERY = 'sabri_file26_discovery_controls_v1';

	private $search;
	private $recommendations;
	private $normalizer;
	private $security;
	private $central_plan;
	private $health;

	use Future_Infra_Trait;
	use Future_Rest_Trait;
	use Future_Utility_Trait;
	use Future_Search_Core_Trait;
	use Future_Multimodal_Trait;
	use Future_Knowledge_Trait;
	use Future_User_Data_Trait;
	use Future_User_Discovery_Trait;
	use Future_Advanced_Trait;

	public function __construct( Search $search, Recommendations $recommendations, Normalizer $normalizer, Security $security, Central_Plan $central_plan, Health $health ) {
		$this->search = $search;
		$this->recommendations = $recommendations;
		$this->normalizer = $normalizer;
		$this->security = $security;
		$this->central_plan = $central_plan;
		$this->health = $health;
	}
}

add_action(
	'plugins_loaded',
	static function () {
		$plugin = Plugin::instance();
		$future = new Future_Intelligence( $plugin->search(), $plugin->recommendations(), new Normalizer(), new Security(), $plugin->central_plan(), $plugin->health() );
		$future->boot();
		$GLOBALS['sabri_file26_future_intelligence'] = $future;
	},
	6
);
