<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * File 26 implementation layer for the 6-Aug-2026 central-plan requirements.
 *
 * This class deliberately remains a derivative/search owner. It does not create
 * canonical content, doctor, clinical, feed, shell, visual-token or payment truth.
 */
final class Central_Plan {
	const META_SAVED_QUERIES = 'sabri_file26_saved_queries_v1';
	const OPTION_CONTENT_GAPS = 'sabri_file26_explicit_content_gaps_v1';
	const OPTION_MIGRATION = 'sabri_file26_central_plan_migration';
	const REST_NAMESPACE = 'sabri-search/v1';

	private $search;
	private $normalizer;
	private $security;
	private $ranking;
	private $doctor_ranking;
	private $health;

	public function __construct( Search $search, Normalizer $normalizer, Security $security, Ranking $ranking, Doctor_Ranking $doctor_ranking, Health $health ) {
		$this->search = $search;
		$this->normalizer = $normalizer;
		$this->security = $security;
		$this->ranking = $ranking;
		$this->doctor_ranking = $doctor_ranking;
		$this->health = $health;
	}

	public function boot() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 30 );
		add_filter( 'rest_post_dispatch', array( $this, 'augment_rest_response' ), 30, 3 );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( DB::CRON_RETENTION, array( $this, 'retention' ), 40 );
		$this->migrate_settings();
	}

	public function register_routes() {
		$this->route( '/advanced-search', 'GET', 'advanced_search', '__return_true' );
		$this->route( '/saved-queries', 'GET', 'saved_queries', 'logged_in' );
		$this->route( '/saved-queries', 'POST', 'save_query', 'logged_in' );
		$this->route( '/saved-queries/(?P<query_id>[a-f0-9-]{36})', 'DELETE', 'delete_query', 'logged_in' );
		$this->route( '/ranking-constitution', 'GET', 'ranking_constitution_route', '__return_true' );
		$this->route( '/content-gap', 'POST', 'submit_content_gap', 'logged_in' );
		$this->route( '/admin/editorial-radar', 'GET', 'editorial_radar', 'can_audit' );
		$this->route( '/admin/central-plan-status', 'GET', 'central_plan_status', 'can_audit' );
	}

	private function route( $path, $methods, $callback, $permission ) {
		register_rest_route(
			self::REST_NAMESPACE,
			$path,
			array(
				'methods' => $methods,
				'callback' => array( $this, $callback ),
				'permission_callback' => '__return_true' === $permission ? '__return_true' : array( $this, $permission ),
			)
		);
	}

	public function logged_in() {
		return is_user_logged_in() ? true : new \WP_Error( 'file26_auth_required', 'Authentication is required.', array( 'status' => 401 ) );
	}

	public function can_audit() {
		return $this->security->can_audit() ? true : new \WP_Error( 'file26_forbidden', 'Search audit capability is required.', array( 'status' => 403 ) );
	}

	/** CV-167: exact phrase/source/date/fields/excludes + saved-query-compatible search. */
	public function advanced_search( \WP_REST_Request $request ) {
		$q = $this->security->sanitize_query( (string) $request->get_param( 'q' ) );
		$exact = $this->security->sanitize_query( (string) $request->get_param( 'exact' ) );
		if ( $exact ) {
			$q = trim( '"' . str_replace( '"', '', $exact ) . '" ' . $q );
		}

		$filters = array();
		foreach ( array( 'entity_type', 'country', 'location', 'availability', 'topic', 'sort', 'author', 'language', 'date_from', 'date_to' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}
		$source = sanitize_key( (string) $request->get_param( 'source' ) );
		if ( $source ) {
			$filters['connector'] = $source;
		}

		$extended = array(
			'fields' => $this->list_param( $request->get_param( 'fields' ), 8 ),
			'excludes' => $this->list_param( $request->get_param( 'excludes' ), 12 ),
			'verification' => sanitize_key( (string) $request->get_param( 'verification' ) ),
			'format' => sanitize_key( (string) $request->get_param( 'format' ) ),
			'access' => sanitize_key( (string) $request->get_param( 'access' ) ),
		);
		$limit = max( 1, min( 30, (int) ( $request->get_param( 'limit' ) ?: 20 ) ) );
		$cursor = (string) $request->get_param( 'cursor' );
		$collected = array();
		$seen = array();
		$pages = 0;
		$partial = false;
		$partial_domains = array();
		$last_cursor = null;
		$last_response = null;

		do {
			$base = $this->search->run(
				array(
					'q' => $q,
					'locale' => $request->get_param( 'locale' ),
					'cursor' => $cursor,
					'limit' => 30,
					'filters' => $filters,
				)
			);
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			$last_response = $base;
			$partial = $partial || ! empty( $base['partial'] );
			$partial_domains = array_merge( $partial_domains, isset( $base['partial_domains'] ) ? (array) $base['partial_domains'] : array() );
			foreach ( (array) $base['results'] as $item ) {
				if ( isset( $seen[ $item['key'] ] ) || ! $this->matches_extended( $item, $q, $extended ) ) {
					continue;
				}
				$seen[ $item['key'] ] = true;
				$collected[] = $item;
				if ( count( $collected ) >= $limit ) {
					break 2;
				}
			}
			$last_cursor = isset( $base['next_cursor'] ) ? $base['next_cursor'] : null;
			$cursor = $last_cursor ? (string) $last_cursor : '';
			$pages++;
		} while ( $cursor && $pages < 10 );

		$response = is_array( $last_response ) ? $last_response : array();
		$response['results'] = array_slice( $collected, 0, $limit );
		$response['advanced_search'] = array(
			'exact_phrase' => $exact,
			'fields' => $extended['fields'],
			'excludes' => $extended['excludes'],
			'verification' => $extended['verification'],
			'format' => $extended['format'],
			'access' => $extended['access'],
			'source' => $source,
			'bounded_source_pages_scanned' => $pages + 1,
		);
		$response['partial'] = $partial || ( $cursor && $pages >= 10 );
		$response['partial_domains'] = array_values( $this->unique_arrays( $partial_domains ) );
		$response['next_cursor'] = $cursor ? $cursor : null;
		return rest_ensure_response( $this->augment_search_result( $response, array( 'q' => $q, 'locale' => $request->get_param( 'locale' ) ) ) );
	}

	private function matches_extended( array $item, $query, array $extended ) {
		$payload = isset( $item['payload'] ) && is_array( $item['payload'] ) ? $item['payload'] : array();
		$haystack = $this->normalizer->normalize( implode( ' ', array_filter( array( isset( $item['title'] ) ? $item['title'] : '', isset( $item['excerpt'] ) ? $item['excerpt'] : '', isset( $item['author_key'] ) ? $item['author_key'] : '', implode( ' ', isset( $item['topics'] ) ? (array) $item['topics'] : array() ) ) ) ) );
		foreach ( $extended['excludes'] as $excluded ) {
			$excluded = $this->normalizer->normalize( $excluded );
			if ( $excluded && false !== $this->strpos( $haystack, $excluded ) ) {
				return false;
			}
		}
		if ( $extended['fields'] && $query ) {
			$field_text = array();
			foreach ( $extended['fields'] as $field ) {
				if ( 'title' === $field ) {
					$field_text[] = isset( $item['title'] ) ? $item['title'] : '';
				} elseif ( in_array( $field, array( 'excerpt', 'summary' ), true ) ) {
					$field_text[] = isset( $item['excerpt'] ) ? $item['excerpt'] : '';
				} elseif ( 'author' === $field ) {
					$field_text[] = isset( $item['author_key'] ) ? $item['author_key'] : '';
				} elseif ( in_array( $field, array( 'topic', 'topics' ), true ) ) {
					$field_text[] = implode( ' ', isset( $item['topics'] ) ? (array) $item['topics'] : array() );
				}
			}
			$field_haystack = $this->normalizer->normalize( implode( ' ', $field_text ) );
			$tokens = $this->normalizer->tokens( $query );
			$matched = false;
			foreach ( $tokens as $token ) {
				if ( false !== $this->strpos( $field_haystack, $token ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				return false;
			}
		}
		if ( $extended['verification'] ) {
			$verified = ! empty( $payload['verified'] ) || ! empty( $payload['verified_doctor'] ) || ! empty( $payload['verified_source'] );
			if ( 'verified' === $extended['verification'] && ! $verified ) {
				return false;
			}
			if ( 'unverified' === $extended['verification'] && $verified ) {
				return false;
			}
		}
		if ( $extended['format'] ) {
			$format = sanitize_key( isset( $payload['format'] ) ? $payload['format'] : ( isset( $payload['media_format'] ) ? $payload['media_format'] : ( isset( $item['entity_type'] ) ? $item['entity_type'] : '' ) ) );
			if ( $format !== $extended['format'] ) {
				return false;
			}
		}
		if ( $extended['access'] ) {
			$access = sanitize_key( isset( $payload['access_mode'] ) ? $payload['access_mode'] : ( isset( $payload['access'] ) ? $payload['access'] : 'public' ) );
			if ( $access !== $extended['access'] ) {
				return false;
			}
		}
		return true;
	}

	/** Explicit, account-owned saved queries. Never used as a hidden ranking signal. */
	public function saved_queries() {
		return rest_ensure_response(
			array(
				'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
				'queries' => array_values( $this->load_saved_queries( get_current_user_id() ) ),
				'used_for_personalization' => false,
			)
		);
	}

	public function save_query( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $this->security->rate_limit( 'saved-query|u:' . $user_id, 30, 60 ) ) {
			return new \WP_Error( 'file26_rate_limited', 'Too many saved-query changes.', array( 'status' => 429 ) );
		}
		$params = (array) $request->get_json_params();
		$q = $this->security->sanitize_query( isset( $params['q'] ) ? $params['q'] : '' );
		if ( '' === $q ) {
			return new \WP_Error( 'file26_saved_query_empty', 'A query is required.', array( 'status' => 400 ) );
		}
		$sensitive = $this->security->contains_sensitive_query( $q );
		if ( $sensitive && empty( $params['confirm_sensitive'] ) ) {
			return new \WP_Error( 'file26_sensitive_save_confirmation', 'This query may contain sensitive intent. Explicit confirmation is required before saving it.', array( 'status' => 400 ) );
		}
		$queries = $this->load_saved_queries( $user_id );
		$id = isset( $params['id'] ) && preg_match( '/^[a-f0-9-]{36}$/', (string) $params['id'] ) ? strtolower( (string) $params['id'] ) : DB::uuid();
		$existing = isset( $queries[ $id ] ) ? $queries[ $id ] : null;
		if ( $existing && isset( $params['expected_version'] ) && (int) $params['expected_version'] !== (int) $existing['version'] ) {
			return new \WP_Error( 'file26_saved_query_version_conflict', 'The saved query changed. Reload before updating.', array( 'status' => 409 ) );
		}
		$now = DB::now();
		$retention_days = $sensitive ? 90 : 365;
		$record = array(
			'id' => $id,
			'name' => substr( sanitize_text_field( isset( $params['name'] ) ? $params['name'] : $q ), 0, 120 ),
			'q' => $q,
			'filters' => $this->sanitize_saved_filters( isset( $params['filters'] ) ? $params['filters'] : array() ),
			'advanced' => $this->sanitize_advanced_saved( isset( $params['advanced'] ) ? $params['advanced'] : array() ),
			'sensitive' => (bool) $sensitive,
			'used_for_personalization' => false,
			'version' => $existing ? (int) $existing['version'] + 1 : 1,
			'created_at' => $existing ? $existing['created_at'] : $now,
			'updated_at' => $now,
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( $retention_days * DAY_IN_SECONDS ) ),
		);
		$queries[ $id ] = $record;
		if ( count( $queries ) > 50 ) {
			uasort( $queries, static function ( $a, $b ) { return strcmp( $a['updated_at'], $b['updated_at'] ); } );
			$queries = array_slice( $queries, -50, null, true );
		}
		update_user_meta( $user_id, self::META_SAVED_QUERIES, $queries );
		$this->security->audit( 'saved_query_changed', array( 'object_type' => 'saved_query', 'object_key' => $id, 'metadata' => array( 'sensitive' => (bool) $sensitive ) ) );
		return rest_ensure_response( $record );
	}

	public function delete_query( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$id = strtolower( (string) $request['query_id'] );
		$queries = $this->load_saved_queries( $user_id );
		if ( ! isset( $queries[ $id ] ) ) {
			return new \WP_Error( 'file26_saved_query_not_found', 'Saved query not found.', array( 'status' => 404 ) );
		}
		unset( $queries[ $id ] );
		update_user_meta( $user_id, self::META_SAVED_QUERIES, $queries );
		$this->security->audit( 'saved_query_deleted', array( 'object_type' => 'saved_query', 'object_key' => $id ) );
		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	/** F26-CEN-02 / CV-169: public, versioned and explainable ranking constitution. */
	public function ranking_constitution_route() {
		return rest_ensure_response( $this->ranking_constitution() );
	}

	public function ranking_constitution() {
		$search = $this->ranking->policy( 'search', 'public' );
		$doctor = $this->doctor_ranking->policy();
		return array(
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'organic_search' => array(
				'policy_version' => $search['version'],
				'signals' => $search['weights'],
				'diversity_limits' => $search['limits'],
			),
			'doctor_ranking' => array(
				'policy_version' => $doctor['version'],
				'signals' => $doctor['weights'],
				'tiers' => array( 'top_10', 'top_100', 'top_1000', 'all_verified' ),
				'recompute' => 'monthly and after an upheld corrective appeal',
				'safe_fallback' => ! empty( $doctor['safe_fallback'] ),
			),
			'constitutional_priorities' => array( 'relevance', 'educational_value', 'source_authority', 'safety', 'provenance', 'freshness', 'diversity', 'user_control' ),
			'prohibited_signals' => array( 'donation', 'payment', 'paid_promotion', 'advertising', 'follower_count', 'founder_favoritism', 'private_messages', 'clinical_records', 'identity_evidence' ),
			'paid_or_sponsored_organic_results' => false,
			'single_free_tier_rank_parity' => true,
			'why_this_result_required' => true,
			'user_controls' => array( 'hide_item', 'hide_author', 'hide_topic', 'not_interested', 'undo', 'reset', 'opt_out' ),
		);
	}

	/** CV-173/172: explicit, non-sensitive content-gap submission with no user identity stored. */
	public function submit_content_gap( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $this->security->rate_limit( 'content-gap|u:' . $user_id, 8, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'file26_rate_limited', 'Too many content-gap submissions.', array( 'status' => 429 ) );
		}
		$params = (array) $request->get_json_params();
		if ( empty( $params['consent'] ) ) {
			return new \WP_Error( 'file26_gap_consent_required', 'Explicit submission consent is required.', array( 'status' => 400 ) );
		}
		$query = $this->security->sanitize_query( isset( $params['q'] ) ? $params['q'] : '' );
		if ( '' === $query || $this->security->contains_sensitive_query( $query ) ) {
			return new \WP_Error( 'file26_gap_sensitive_or_empty', 'Empty or sensitive queries are not retained as editorial content gaps.', array( 'status' => 400 ) );
		}
		$normalized = substr( $this->normalizer->normalize( $query ), 0, 180 );
		$key = hash_hmac( 'sha256', $normalized, wp_salt( 'auth' ) );
		$registry = get_option( self::OPTION_CONTENT_GAPS, array() );
		$registry = is_array( $registry ) ? $registry : array();
		$now = DB::now();
		$current = isset( $registry[ $key ] ) && is_array( $registry[ $key ] ) ? $registry[ $key ] : array();
		$registry[ $key ] = array(
			'key' => $key,
			'query' => $normalized,
			'locale' => substr( sanitize_text_field( isset( $params['locale'] ) ? $params['locale'] : determine_locale() ), 0, 20 ),
			'count' => isset( $current['count'] ) ? min( 1000000, (int) $current['count'] + 1 ) : 1,
			'first_seen' => isset( $current['first_seen'] ) ? $current['first_seen'] : $now,
			'last_seen' => $now,
			'status' => 'open',
			'identity_stored' => false,
			'source' => 'explicit_user_submission',
		);
		if ( count( $registry ) > 200 ) {
			uasort( $registry, static function ( $a, $b ) { return strcmp( $a['last_seen'], $b['last_seen'] ); } );
			$registry = array_slice( $registry, -200, null, true );
		}
		update_option( self::OPTION_CONTENT_GAPS, $registry, false );
		$this->record_aggregate_metric( 'explicit_content_gap', isset( $params['locale'] ) ? $params['locale'] : determine_locale(), 1, 0 );
		return rest_ensure_response( array( 'accepted' => true, 'identity_stored' => false, 'retention_days' => 90 ) );
	}

	/** CV-172: privacy-minimized editorial radar, built only from aggregate File 26 data. */
	public function editorial_radar( \WP_REST_Request $request ) {
		global $wpdb;
		$days = max( 1, min( 90, (int) ( $request->get_param( 'days' ) ?: 30 ) ) );
		$from = gmdate( 'Y-m-d', time() - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
		$table = DB::table( 'metrics' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT metric_key,locale,SUM(count_value) AS total_count,SUM(sum_value) AS total_value,MAX(metric_date) AS latest_date FROM $table WHERE metric_date >= %s GROUP BY metric_key,locale ORDER BY total_count DESC LIMIT 250",
				$from
			),
			ARRAY_A
		);
		$gaps = get_option( self::OPTION_CONTENT_GAPS, array() );
		$gaps = is_array( $gaps ) ? array_values( $gaps ) : array();
		usort( $gaps, static function ( $a, $b ) { return (int) $a['count'] === (int) $b['count'] ? strcmp( $b['last_seen'], $a['last_seen'] ) : ( (int) $b['count'] <=> (int) $a['count'] ); } );
		return rest_ensure_response(
			array(
				'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
				'window_days' => $days,
				'aggregate_metrics' => $rows,
				'rising_explicit_content_gaps' => array_slice( $gaps, 0, 50 ),
				'private_user_identity_included' => false,
				'raw_sensitive_query_history_included' => false,
				'trend_truth_owner' => 'File 15; this File 26 surface is editorial/search telemetry only',
				'health' => $this->health->snapshot(),
			)
		);
	}

	public function central_plan_status() {
		return rest_ensure_response(
			array(
				'version' => SABRI_FILE26_VERSION,
				'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
				'governing_addendum' => '6-Aug-2026 three-central-plan continuous-value requirements',
				'coded_requirements' => array( 'CV-164', 'CV-165', 'CV-166', 'CV-167', 'CV-168', 'CV-169', 'CV-172', 'CV-173', 'CV-174', 'CV-175', 'F26-CEN-01', 'F26-CEN-02' ),
				'consumer_only_boundaries' => array( 'CV-170' => 'File 06/15 canonical knowledge/research owner', 'CV-171' => 'File 15 trend owner' ),
				'brand_primary_fallback' => '#087A4E',
				'single_free_tier' => true,
				'live_status_claimed' => false,
			)
		);
	}

	/** Add central-plan safety/recovery/freshness contracts to the ordinary REST search response. */
	public function augment_rest_response( $response, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request || '/sabri-search/v1/search' !== $request->get_route() || is_wp_error( $response ) ) {
			return $response;
		}
		$response = rest_ensure_response( $response );
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		$data = $this->augment_search_result( $data, array( 'q' => $request->get_param( 'q' ), 'locale' => $request->get_param( 'locale' ) ) );
		$response->set_data( $data );
		return $response;
	}

	public function augment_search_result( $response, array $request = array() ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return $response;
		}
		$query = $this->security->sanitize_query( isset( $request['q'] ) ? $request['q'] : ( isset( $response['query'] ) ? $response['query'] : '' ) );
		$locale = substr( sanitize_text_field( isset( $request['locale'] ) && $request['locale'] ? $request['locale'] : determine_locale() ), 0, 20 );
		$safety = $this->safety_for_query( $query, $locale );
		$response['safety'] = $safety;
		$response['ranking_constitution'] = array(
			'policy_version' => isset( $response['policy_version'] ) ? $response['policy_version'] : $this->ranking->policy_version(),
			'public_endpoint' => rest_url( self::REST_NAMESPACE . '/ranking-constitution' ),
			'paid_or_donor_influence' => false,
		);
		$response['free_tier'] = array( 'search_paywall' => false, 'ranking_paywall' => false, 'donation_signal' => false );
		$response['brand_contract'] = array( 'primary_fallback' => '#087A4E', 'visual_owner' => 'File 25', 'shell_owner' => 'File 20' );
		$response = $this->augment_freshness( $response );
		if ( empty( $response['results'] ) ) {
			$response['zero_result_recovery'] = $this->zero_result_recovery( $query, $locale, $safety );
		} else {
			$response['zero_result_recovery'] = null;
		}
		if ( 'general' !== $safety['risk_class'] ) {
			$this->record_aggregate_metric( 'search_safety_' . $safety['risk_class'], $locale, 1, 0 );
		}
		return $response;
	}

	/** CV-174: no fabricated emergency contacts; owner-verified resource may be injected by filter. */
	public function safety_for_query( $query, $locale = 'und' ) {
		$normalized = $this->normalizer->normalize( $query );
		$risk = 'general';
		$reason = 'general_discovery';
		$patterns = array(
			'emergency' => array( 'suicide', 'kill myself', 'cannot breathe', "can't breathe", 'severe bleeding', 'unconscious', 'chest pain', 'خودکشی', 'جان دینا', 'سانس نہیں', 'سانس بند', 'شدید خون', 'سینے میں شدید درد', 'بے ہوش' ),
			'harmful' => array( 'how to poison', 'how to kill', 'make explosive', 'زہر دے', 'قتل کیسے', 'بم بن' ),
			'clinical' => array( 'dose', 'dosage', 'prescription', 'potency', 'stop medicine', 'خوراک', 'پوٹینسی', 'نسخہ', 'دوا بند' ),
		);
		foreach ( array( 'emergency', 'harmful', 'clinical' ) as $class ) {
			foreach ( $patterns[ $class ] as $pattern ) {
				if ( $pattern && false !== $this->strpos( $normalized, $this->normalizer->normalize( $pattern ) ) ) {
					$risk = $class;
					$reason = 'matched_' . $class . '_safety_policy';
					break 2;
				}
			}
		}
		$resource = apply_filters( 'sabri_file26_verified_emergency_resource', null, $locale, $risk );
		$resource_verified = is_array( $resource ) && ! empty( $resource['verified'] ) && ! empty( $resource['label'] );
		$guidance = null;
		if ( 'emergency' === $risk ) {
			$guidance = 'If there may be immediate danger or a medical emergency, do not wait for platform search or an appointment. Seek qualified local emergency care now.';
		} elseif ( 'harmful' === $risk ) {
			$guidance = 'Search results are constrained to safety-oriented, lawful information and cannot provide instructions intended to harm a person.';
		} elseif ( 'clinical' === $risk ) {
			$guidance = 'Search is educational and does not replace individualized diagnosis, prescription, dosage or emergency care.';
		}
		return array(
			'risk_class' => $risk,
			'reason_code' => $reason,
			'educational_only' => true,
			'guidance' => $guidance,
			'local_resource' => $resource_verified ? $resource : null,
			'local_resource_verified' => $resource_verified,
			'fabricated_local_details' => false,
		);
	}

	private function zero_result_recovery( $query, $locale, array $safety ) {
		global $wpdb;
		$candidates = array();
		foreach ( $this->normalizer->retrieval_terms( $query ) as $term ) {
			$term = sanitize_text_field( $term );
			if ( $term && $this->normalizer->normalize( $term ) !== $this->normalizer->normalize( $query ) ) {
				$candidates[] = $term;
			}
		}
		$candidates = array_slice( array_values( array_unique( $candidates ) ), 0, 6 );
		$related = array();
		$normalized = $this->normalizer->normalize( $query );
		if ( $normalized && strlen( $normalized ) >= 2 ) {
			$terms = DB::table( 'terms' );
			$aliases = DB::table( 'term_aliases' );
			$like = '%' . $wpdb->esc_like( substr( $normalized, 0, 80 ) ) . '%';
			$related = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT t.term_uuid,t.preferred_label,t.language FROM $terms t LEFT JOIN $aliases a ON a.term_uuid=t.term_uuid AND a.status='active' WHERE t.status='active' AND (t.preferred_label LIKE %s OR a.alias_normalized LIKE %s) ORDER BY t.preferred_label LIMIT 6",
					$like,
					$like
				),
				ARRAY_A
			);
		}
		$can_submit = is_user_logged_in() && 'general' === $safety['risk_class'] && ! $this->security->contains_sensitive_query( $query );
		return array(
			'spelling_or_transliteration_candidates' => $candidates,
			'related_topics' => $related,
			'actions' => array(
				'adjust_filters' => true,
				'browse_topics' => true,
				'submit_content_gap' => $can_submit,
				'ask_expert_destination_owner' => 'File 17/approved support owner',
			),
			'fabricated_result' => false,
			'query_retained_for_gap_without_explicit_consent' => false,
		);
	}

	/** CV-175 / F26-CEN-01: report known synchronization evidence; unknown stays unknown. */
	private function augment_freshness( array $response ) {
		global $wpdb;
		$results = isset( $response['results'] ) && is_array( $response['results'] ) ? $response['results'] : array();
		if ( ! $results ) {
			$response['index_freshness'] = array( 'known' => 0, 'within_slo' => 0, 'stale' => 0, 'unknown' => 0 );
			return $response;
		}
		$keys = array();
		foreach ( $results as $item ) {
			if ( ! empty( $item['key'] ) && preg_match( '/^[a-f0-9]{64}$/', $item['key'] ) ) {
				$keys[] = $item['key'];
			}
		}
		if ( ! $keys ) {
			return $response;
		}
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$documents = DB::table( 'documents' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT canonical_key,connector_slug,entity_type,locale,state,visibility,freshness_at,indexed_at,payload FROM $documents WHERE canonical_key IN ($placeholders)", $keys ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ $row['canonical_key'] ] = $row;
		}
		$summary = array( 'known' => 0, 'within_slo' => 0, 'stale' => 0, 'unknown' => 0 );
		foreach ( $response['results'] as &$item ) {
			$row = isset( $map[ $item['key'] ] ) ? $map[ $item['key'] ] : null;
			$status = 'unknown';
			$lag = null;
			$source_updated = null;
			if ( $row ) {
				$payload = json_decode( $row['payload'], true );
				$payload = is_array( $payload ) ? $payload : array();
				$source_updated = ! empty( $payload['source_updated_at'] ) ? $payload['source_updated_at'] : null;
				if ( $source_updated && strtotime( $source_updated . ' UTC' ) && strtotime( $row['indexed_at'] . ' UTC' ) ) {
					$lag = max( 0, strtotime( $row['indexed_at'] . ' UTC' ) - strtotime( $source_updated . ' UTC' ) );
					$default_slo = in_array( $row['entity_type'], array( 'news', 'post', 'reel' ), true ) ? 900 : 3600;
					$slo = (int) apply_filters( 'sabri_file26_index_freshness_slo_seconds', $default_slo, $row['entity_type'], $row['connector_slug'] );
					$status = $lag <= max( 60, $slo ) ? 'within_slo' : 'stale';
					$summary['known']++;
					$summary[ $status ]++;
				} else {
					$summary['unknown']++;
				}
				$item['integrity'] = array(
					'index_eligibility_checked' => true,
					'canonical_owner_reference_preserved' => true,
					'owner_click_revalidation_required' => true,
					'rights_revalidation_required' => true,
					'indexed_at' => $row['indexed_at'],
					'source_updated_at' => $source_updated,
					'index_sync_lag_seconds' => $lag,
					'freshness_status' => $status,
				);
			}
		}
		unset( $item );
		$response['index_freshness'] = $summary;
		return $response;
	}

	private function record_aggregate_metric( $metric, $locale, $count, $sum ) {
		global $wpdb;
		$metric = substr( sanitize_key( $metric ), 0, 96 );
		$locale = substr( sanitize_text_field( $locale ), 0, 20 );
		$bucket = hash_hmac( 'sha256', $metric . '|' . $locale . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) );
		$table = DB::table( 'metrics' );
		$sql = $wpdb->prepare(
			"INSERT INTO $table (metric_date,metric_key,bucket_hash,locale,count_value,sum_value,updated_at) VALUES (%s,%s,%s,%s,%d,%f,%s) ON DUPLICATE KEY UPDATE count_value=count_value+VALUES(count_value),sum_value=sum_value+VALUES(sum_value),updated_at=VALUES(updated_at)",
			gmdate( 'Y-m-d' ), $metric, $bucket, $locale, max( 0, (int) $count ), max( 0, (float) $sum ), DB::now()
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function load_saved_queries( $user_id ) {
		$value = get_user_meta( (int) $user_id, self::META_SAVED_QUERIES, true );
		$value = is_array( $value ) ? $value : array();
		$now = time();
		$changed = false;
		foreach ( $value as $id => $record ) {
			if ( ! is_array( $record ) || empty( $record['expires_at'] ) || strtotime( $record['expires_at'] . ' UTC' ) < $now ) {
				unset( $value[ $id ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			update_user_meta( (int) $user_id, self::META_SAVED_QUERIES, $value );
		}
		return $value;
	}

	private function sanitize_saved_filters( $filters ) {
		$filters = is_array( $filters ) ? $filters : array();
		$clean = array();
		foreach ( array( 'entity_type', 'country', 'location', 'availability', 'connector', 'domain', 'topic', 'sort', 'language' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$clean[ $key ] = substr( sanitize_text_field( $filters[ $key ] ), 0, 191 );
			}
		}
		if ( ! empty( $filters['author'] ) ) {
			$clean['author'] = substr( sanitize_text_field( $filters['author'] ), 0, 191 );
		}
		foreach ( array( 'date_from', 'date_to' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters[ $key ] ) ) {
				$clean[ $key ] = $filters[ $key ];
			}
		}
		return $clean;
	}

	private function sanitize_advanced_saved( $advanced ) {
		$advanced = is_array( $advanced ) ? $advanced : array();
		return array(
			'exact' => substr( sanitize_text_field( isset( $advanced['exact'] ) ? $advanced['exact'] : '' ), 0, 200 ),
			'fields' => $this->list_param( isset( $advanced['fields'] ) ? $advanced['fields'] : array(), 8 ),
			'excludes' => $this->list_param( isset( $advanced['excludes'] ) ? $advanced['excludes'] : array(), 12 ),
			'verification' => sanitize_key( isset( $advanced['verification'] ) ? $advanced['verification'] : '' ),
			'format' => sanitize_key( isset( $advanced['format'] ) ? $advanced['format'] : '' ),
			'access' => sanitize_key( isset( $advanced['access'] ) ? $advanced['access'] : '' ),
			'source' => sanitize_key( isset( $advanced['source'] ) ? $advanced['source'] : '' ),
		);
	}

	private function list_param( $value, $max ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY );
		}
		$value = is_array( $value ) ? $value : array();
		$out = array();
		foreach ( array_slice( $value, 0, (int) $max ) as $item ) {
			$item = substr( sanitize_text_field( $item ), 0, 120 );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function unique_arrays( array $items ) {
		$out = array();
		$seen = array();
		foreach ( $items as $item ) {
			$key = hash( 'sha256', wp_json_encode( $item ) );
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$out[] = $item;
			}
		}
		return $out;
	}

	private function strpos( $haystack, $needle ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( (string) $haystack, (string) $needle, 0, 'UTF-8' );
		}
		return strpos( (string) $haystack, (string) $needle );
	}

	private function migrate_settings() {
		$current = get_option( DB::OPTION_SETTINGS, array() );
		$current = is_array( $current ) ? $current : array();
		$changed = false;
		if ( empty( $current['primary_color'] ) || in_array( strtolower( (string) $current['primary_color'] ), array( '#138a36', '#ff8a1f' ), true ) ) {
			$current['primary_color'] = '#087A4E';
			$changed = true;
		}
		$defaults = array(
			'single_free_tier' => true,
			'paid_organic_results_enabled' => false,
			'donation_ranking_signal_enabled' => false,
			'saved_query_retention_days' => 365,
			'sensitive_saved_query_retention_days' => 90,
			'explicit_gap_retention_days' => 90,
		);
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $current ) ) {
				$current[ $key ] = $value;
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( DB::OPTION_SETTINGS, $current, false );
		}
		update_option( self::OPTION_MIGRATION, '1.2.0', false );
	}

	public function retention() {
		$registry = get_option( self::OPTION_CONTENT_GAPS, array() );
		$registry = is_array( $registry ) ? $registry : array();
		$cutoff = time() - ( 90 * DAY_IN_SECONDS );
		$changed = false;
		foreach ( $registry as $key => $record ) {
			if ( ! is_array( $record ) || empty( $record['last_seen'] ) || strtotime( $record['last_seen'] . ' UTC' ) < $cutoff ) {
				unset( $registry[ $key ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION_CONTENT_GAPS, $registry, false );
		}
	}

	public function register_exporter( $exporters ) {
		$exporters['sabri-file26-saved-queries'] = array( 'exporter_friendly_name' => __( 'File 26 saved search queries', 'sabri-file26' ), 'callback' => array( $this, 'privacy_export' ) );
		return $exporters;
	}

	public function register_eraser( $erasers ) {
		$erasers['sabri-file26-saved-queries'] = array( 'eraser_friendly_name' => __( 'File 26 saved search queries', 'sabri-file26' ), 'callback' => array( $this, 'privacy_erase' ) );
		return $erasers;
	}

	public function privacy_export( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user || (int) $page > 1 ) {
			return array( 'data' => array(), 'done' => true );
		}
		$data = array();
		foreach ( $this->load_saved_queries( $user->ID ) as $record ) {
			$data[] = array(
				'group_id' => 'sabri-file26-saved-queries',
				'group_label' => __( 'Saved search queries', 'sabri-file26' ),
				'item_id' => 'saved-query-' . $record['id'],
				'data' => array(
					array( 'name' => __( 'Name', 'sabri-file26' ), 'value' => $record['name'] ),
					array( 'name' => __( 'Query', 'sabri-file26' ), 'value' => $record['q'] ),
					array( 'name' => __( 'Filters', 'sabri-file26' ), 'value' => wp_json_encode( $record['filters'] ) ),
					array( 'name' => __( 'Updated', 'sabri-file26' ), 'value' => $record['updated_at'] ),
				),
			);
		}
		return array( 'data' => $data, 'done' => true );
	}

	public function privacy_erase( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user || (int) $page > 1 ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$had = (bool) get_user_meta( $user->ID, self::META_SAVED_QUERIES, true );
		delete_user_meta( $user->ID, self::META_SAVED_QUERIES );
		return array( 'items_removed' => $had, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}
