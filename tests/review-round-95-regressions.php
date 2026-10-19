<?php
/** Round 95 multimodal/private/external trust-boundary regression guards. */
$root = dirname( __DIR__ );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$rest = file_get_contents( $root . '/includes/trait-file26-future-rest-trait.php' );
$advanced = file_get_contents( $root . '/includes/trait-file26-future-advanced.php' );
$multimodal = file_get_contents( $root . '/includes/trait-file26-future-multimodal.php' );
$checks = 0;
function f26_r95_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r95_assert( false !== strpos( $rest, "normalize_authorization( apply_filters( 'sabri_file26_future_step_up_verified'" ), 'R95: Future private-vault fallback step-up is strict boolean authorization' );
f26_r95_assert( false !== strpos( $advanced, "normalize_authorization( apply_filters( 'sabri_file26_external_evidence_connector_approved'" ), 'R95: external evidence connector approval is strict boolean authorization' );
f26_r95_assert( substr_count( $rest . $advanced, "normalize_authorization( isset( \$params['external_consent'] )" ) >= 2, 'R95: external evidence consent is strict at dispatch and handler boundaries' );
f26_r95_assert( false !== strpos( $multimodal, "\$patient_image = isset( \$asset['patient_image'] ) && \$this->security->normalize_authorization" ) && false !== strpos( $multimodal, "\$diagnose = isset( \$params['diagnose'] ) && \$this->security->normalize_authorization" ), 'R95: multimodal diagnosis flags do not use PHP loose truthiness' );
f26_r95_assert( false !== strpos( $multimodal, "if ( '' === \$audio_ref )" ), 'R95: empty sanitized voice reference cannot reach transcription provider' );
f26_r95_assert( false !== strpos( $security, "\$allowed=\$this->normalize_authorization(apply_filters('sabri_file26_allowed_external_resource_url'" ), 'R95: external resource allow filter fails closed on malformed truthy values' );
echo "PASS: $checks Round 95 regression assertions\n";
