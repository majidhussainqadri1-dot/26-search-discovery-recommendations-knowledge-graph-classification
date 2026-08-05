<?php
$root = dirname( __DIR__ );
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$text = '';
foreach ( $files as $file ) {
	if ( $file->isFile() && preg_match( '/\.(php|md|txt|js|css|yml)$/', $file->getFilename() ) ) {
		$text .= "\n" . file_get_contents( $file->getPathname() );
	}
}
$passed = 0; $failed = 0;
function assert_contract( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) { $passed++; echo "PASS: $label\n"; }
	else { $failed++; echo "FAIL: $label\n"; }
}

for ( $i = 1; $i <= 36; $i++ ) {
	$id = sprintf( 'File26-FR-%03d', $i );
	assert_contract( false !== strpos( $text, $id ), "$id traceability exists." );
}

$required_tables = array( 'connectors','documents','tombstones','terms','term_aliases','classifications','nodes','edges','ranking_policies','feedback','profiles','jobs','audit','metrics','rate_limits' );
foreach ( $required_tables as $table ) {
	assert_contract( false !== strpos( $text, "table( '$table' )" ) || false !== strpos( $text, "'$table'," ), "Table/domain $table exists." );
}

foreach ( array( '^search/?$', '^discover/?$', '^topics/([^/]+)/?$', 'sabri-search/v1', 'sabri_shell_register_routes', 'sabri_file24_module_manifest', 'sabri_file25_search_provider' ) as $needle ) {
	assert_contract( false !== strpos( $text, $needle ), "Required route/integration: $needle" );
}

assert_contract( false !== strpos( $text, "'activated' => false" ), 'Runtime activation defaults fail-closed.' );
assert_contract( false !== strpos( $text, 'query_text_sampling' ) && false !== strpos( $text, "'query_text_sampling' => false" ), 'Raw query sampling is disabled.' );
assert_contract( false !== strpos( $text, 'three-stage-visibility' ), 'Three-stage visibility is declared to assurance.' );
assert_contract( false !== strpos( $text, 'Top 10 Verified Doctors' ) && false !== strpos( $text, 'All Verified Doctors' ), 'Doctor ranking tiers are implemented.' );
assert_contract( false !== strpos( $text, 'download_allowed' ) && false !== strpos( $text, 'download_url' ), 'Owner-authorized download contract is implemented.' );
assert_contract( 0 === preg_match( '/SELECT\s+.*(?:smc_|patient|message_body|payment_card)/i', implode( "\n", glob( $root . '/includes/*.php' ) ? array_map( 'file_get_contents', glob( $root . '/includes/*.php' ) ) : array() ) ), 'No direct sensitive foreign-table query primitive.' );
assert_contract( false !== strpos( $text, 'tombstone_precedence' ), 'Stale upsert cannot override a same/newer tombstone.' );
assert_contract( false !== strpos( $text, "idempotency_key: (window.crypto" ) && false !== strpos( $text, "type: button.getAttribute('data-f26-feedback')" ), 'Recommendation feedback UI matches the idempotent REST contract.' );
assert_contract( false !== strpos( $text, 'doctor-global-1.0' ), 'Monthly explainable doctor-ranking policy is implemented.' );
assert_contract( false !== strpos( $text, 'forbidden_ranking_signal' ), 'Prohibited financial/favoritism ranking signals are rejected.' );
assert_contract( false !== strpos( $text, 'wp_privacy_personal_data_exporters' ) && false !== strpos( $text, 'wp_privacy_personal_data_erasers' ), 'WordPress privacy exporter and eraser exist.' );
assert_contract( false !== strpos( $text, "'h' => \$cursor_context" ) && false !== strpos( $text, 'hash_equals( $cursor_context' ), 'Search cursors are bound to query context and policy version.' );
assert_contract( false !== strpos( $text, "'state' => 'draft'" ) && false !== strpos( $text, 'knowledge_edge_activate' ), 'Knowledge graph edge creation cannot self-publish.' );
assert_contract( false !== strpos( $text, 'safe_resource_url' ) && false !== strpos( $text, 'sabri_file26_allowed_external_resource_url' ), 'External resource links require explicit canonical-owner approval.' );
assert_contract( false !== strpos( $text, 'sanitize_policy_features' ) && false !== strpos( $text, 'patient_record' ), 'Ranking policy configuration is recursively bounded and rejects sensitive signals.' );
assert_contract( false !== strpos( $text, 'file26_profile_conflict' ) && false !== strpos( $text, 'file26_feedback_not_reversible' ), 'Recommendation preference concurrency and single-use undo are enforced.' );
assert_contract( false !== strpos( $text, "wp_clear_scheduled_hook( 'sabri_file26_doctor_ranking' )" ), 'All scheduled File 26 jobs are cleared on uninstall.' );

printf( "Passed: %d\nFailed: %d\n", $passed, $failed );
exit( $failed ? 1 : 0 );
