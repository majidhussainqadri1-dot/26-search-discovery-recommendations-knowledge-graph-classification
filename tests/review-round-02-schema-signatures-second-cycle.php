<?php
/** Second-cycle Round 2 regression: schema names alone cannot satisfy physical integrity. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-schema-integrity.php' );
$checks = array(
	array( $source, 'DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,CHARACTER_MAXIMUM_LENGTH', 'physical column metadata includes type/nullability/length' ),
	array( $source, 'NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME', 'physical index metadata includes uniqueness/order/columns' ),
	array( $source, 'critical_columns()', 'critical column signatures are declared' ),
	array( $source, 'index_signatures()', 'index signatures are declared' ),
	array( $source, "'incompatible_columns'", 'column incompatibility is surfaced' ),
	array( $source, "'incompatible_indexes'", 'index incompatibility is surfaced' ),
	array( $source, "'canonical_key'=>array(true,array('canonical_key'))", 'canonical document key remains uniquely indexed' ),
	array( $source, "'runnable'=>array(false,array('status','available_at'))", 'job runnable index order is verified' ),
	array( $source, "'bucket_window'=>array(true,array('bucket_key','window_start'))", 'rate-limit uniqueness boundary is verified' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 02 schema signature regression passed.\n";
