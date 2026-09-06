<?php
/** Round 18 regression: docs reflect the current code, ownership and release-state law. */
$root = dirname( __DIR__ );
$connector = file_get_contents( $root . '/docs/CONNECTOR-CONTRACT.md' );
$rest = file_get_contents( $root . '/docs/REST-CONTRACT.md' );
$trace = file_get_contents( $root . '/docs/REQUIREMENTS-TRACEABILITY.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$staging = file_get_contents( $root . '/docs/STAGING-ACCEPTANCE.md' );
$checks = array(
	array( $connector, '# Connector Contract v1.2', 'connector documentation matches contract version 1.2' ),
	array( $connector, 'capability-bearing signed delivery URLs', 'connector docs prohibit cached delivery capabilities' ),
	array( $connector, 'non-progressing empty batch', 'connector rebuild progress law is documented' ),
	array( $rest, 'no-store` by default', 'REST docs match revocation-safe cache defaults' ),
	array( $rest, 'Database/read failures are not converted into empty-success', 'REST failure-truth law is documented' ),
	array( $trace, 'CV-164 / F26-CEN-01', 'central-plan addendum is traced' ),
	array( $trace, 'CV-175', 'free-tier/prohibited-signal addendum is traced' ),
	array( $trace, '`Specified`, `Coded`, `Packaged`', 'implementation-state separation is explicit' ),
	array( $architecture, 'exact source, deterministic runtime-only package, staging deployment and live runtime', 'architecture separates repository/package/deployment realities' ),
	array( $staging, '**Repository HEAD:**', 'staging record captures exact repository head' ),
	array( $staging, '**Deployed Version:**', 'staging record separately captures deployed version' ),
	array( $staging, '**DB Version:**', 'staging record captures DB truth' ),
	array( $staging, '**Migration State:**', 'staging record captures migration truth' ),
	array( $staging, '**Deployment Parity:**', 'staging requires deployment parity evidence' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 18 traceability and owner-contract regression passed.\n";
