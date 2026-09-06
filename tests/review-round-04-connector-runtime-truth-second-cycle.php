<?php
/** Second-cycle Round 4 regression: persisted lifecycle and health truth require live runtime evidence. */
$root = dirname( __DIR__ );
$connectors = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$governance = file_get_contents( $root . '/includes/class-file26-governance.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$checks = array(
	array( $connectors, '$status = $this->effective_status( $public_manifest );', 'effective persisted lifecycle is resolved before write' ),
	array( $connectors, '$persisted = $this->persist( $public_manifest );', 'connector persistence is explicit after runtime checks' ),
	array( $connectors, 'file26_connector_runtime_incomplete', 'incomplete eligible runtime fails closed' ),
	array( $connectors, 'connector_health_persist_failed', 'health persistence failure is audited' ),
	array( $connectors, '$detail[\'persistence_error\'] = true', 'health persistence failure is exposed as degraded detail' ),
	array( $governance, 'private $connectors;', 'governance owns current runtime connector registry' ),
	array( $governance, '$runtime = $this->connectors->get( $slug );', 'promotion resolves current runtime adapter' ),
	array( $governance, 'in_array( $target, array( \'shadow\', \'approved\', \'active\' ), true )', 'index/production promotion is runtime-gated' ),
	array( $governance, '$this->connectors->set_runtime_status( $slug, $target )', 'successful DB transition syncs runtime state' ),
	array( $plugin, 'new Governance( $this->security, $this->taxonomy, $this->graph, $this->connectors )', 'governance receives the authoritative runtime registry' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
$runtime_check = strpos( $connectors, 'file26_connector_runtime_incomplete' );
$persist = strpos( $connectors, '$persisted = $this->persist( $public_manifest );' );
if ( false === $runtime_check || false === $persist || $runtime_check > $persist ) {
	fwrite( STDERR, "FAIL: connector eligible-runtime validation must occur before persistence\n" ); $failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 04 connector runtime truth regression passed.\n";
