<?php
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-security.php' );
$checks = array(
	"$payload['v'] = 2" => 'cursor contract version is hardened',
	"$payload['iat'] = $now" => 'cursor has issue time',
	"$payload['exp'] = $now + $ttl" => 'cursor has bounded expiry',
	"$payload['sub'] = $this->cursor_subject()" => 'cursor binds authenticated subject/public audience',
	"$payload['exp'] < $now" => 'expired cursor is rejected',
	"hash_equals( (string) $payload['sub'], $this->cursor_subject() )" => 'cross-subject cursor replay is rejected',
	"esc_url_raw( $url, array( 'https' ) )" => 'external resource URLs are HTTPS-only',
	"'https' !== strtolower( $parts['scheme'] )" => 'non-HTTPS external scheme fails closed',
	'return false !== $wpdb->insert' => 'audit persistence reports failure to caller',
);
$failed = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "FAIL: $label\n" );
		$failed++;
	} else {
		echo "PASS: $label\n";
	}
}
exit( $failed ? 1 : 0 );
