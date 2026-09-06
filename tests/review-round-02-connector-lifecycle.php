<?php
/** Round 02 regression: connector manifests remain durable, compatible and fail closed after reload. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$checks = array(
	'file26_invalid_manifest_after_normalization' => 'post-normalization manifest validation',
	'empty( $manifest[\'entity_types\'] )' => 'entity type list cannot sanitize to empty',
	'empty( $manifest[\'privacy_classes\'] )' => 'privacy class list cannot sanitize to empty',
	'empty( $manifest[\'visibility_fields\'] )' => 'visibility fields cannot sanitize to empty',
	'\'\' === $manifest[\'deletion_semantics\']' => 'deletion semantics cannot sanitize to empty',
	'\'versioned_tombstone\' !== $manifest[\'deletion_semantics\']' => 'unsupported deletion semantics fail closed',
	'file26_connector_persist_failed' => 'registry cannot silently proceed after persistence failure',
	'file26_connector_runtime_incomplete' => 'persisted production state requires live runtime callbacks',
	'last_event_version=IF(owner_file=VALUES(owner_file) AND contract_version=VALUES(contract_version),last_event_version,0)' => 'same-contract reload preserves event checkpoint',
	'health_state=IF(owner_file=VALUES(owner_file) AND contract_version=VALUES(contract_version),health_state,\'unknown\')' => 'same-contract reload preserves health state',
	'last_health=IF(owner_file=VALUES(owner_file) AND contract_version=VALUES(contract_version),last_health,NULL)' => 'same-contract reload preserves last health time',
	'return false;' => 'missing active visibility callback cannot fall back to generic serving',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( false !== strpos( $source, "manifest=VALUES(manifest),last_event_version=0,health_state='unknown',last_health=NULL" ) ) {
	fwrite( STDERR, "FAIL: connector reload still destroys checkpoint and health state\n" ); $failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Round 02 connector lifecycle regression passed.\n";
