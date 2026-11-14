<?php
/**
 * File26 V12.3 numeric integrity: no overflow/saturation/NaN/INF in source
 * ranking signals, downstream JSON checksums, or unsigned count payloads.
 *
 * Source-isolated PHP test. No DB, no network, no Live user records.
 */
namespace {
    define('ABSPATH', __DIR__.'/');
    if (!class_exists('WP_Error')) {
        class WP_Error {
            private $code;
            public function __construct($code, $message = '') { $this->code=$code; }
            public function get_error_code() { return $this->code; }
        }
    }
}
namespace Sabri\File26 {
    require_once dirname(__DIR__).'/includes/class-file26-indexer.php';
}
namespace {
    $obj = (new \ReflectionClass('Sabri\File26\Indexer'))->newInstanceWithoutConstructor();
    $payload = new \ReflectionMethod($obj, 'sanitize_payload');
    $numeric = new \ReflectionMethod($obj, 'strict_finite_number');
    $strict = new \ReflectionMethod($obj, 'strict_integer');
    $ok = function($condition, $message) {
        if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
        echo "PASS: $message\n";
    };

    $bad = $payload->invoke($obj, array('global_doctor_rank'=>'18446744073709551615'));
    $ok($bad instanceof \WP_Error && $bad->get_error_code()==='file26_invalid_payload_integer', 'Unsigned BIGINT overflow cannot coerce verified doctor rank to PHP_INT_MAX');

    $bad = $payload->invoke($obj, array('source_count'=>'999999999999999999999999'));
    $ok($bad instanceof \WP_Error && $bad->get_error_code()==='file26_invalid_payload_integer', 'Source count overflow rejected rather than silently saturated');

    $bad = $payload->invoke($obj, array('global_doctor_rank'=>'5.5'));
    $ok($bad instanceof \WP_Error && $bad->get_error_code()==='file26_invalid_payload_integer', 'Fractional rank rejected without truncation');

    $bad = $payload->invoke($obj, array('source_count'=>array(100)));
    $ok($bad instanceof \WP_Error && $bad->get_error_code()==='file26_invalid_payload_integer', 'Array-shaped source count rejected before cast');

    $good = $payload->invoke($obj, array('global_doctor_rank'=>'00010','source_count'=>42));
    $ok(is_array($good) && $good['global_doctor_rank']===10 && $good['source_count']===42, 'Normal bounded integer payloads retain exact values');

    foreach (array(INF, -INF, NAN, 'NaN', 'INF', 'not-a-score', array(0.9)) as $value) {
        $result=$payload->invoke($obj,array('doctor_rank_score'=>$value));
        $ok($result instanceof \WP_Error && $result->get_error_code()==='file26_invalid_payload_score', 'Malformed doctor score refused');
        $result=$payload->invoke($obj,array('qualification_score'=>$value));
        $ok($result instanceof \WP_Error && $result->get_error_code()==='file26_invalid_payload_score', 'Malformed qualification score refused');
    }
    $good=$payload->invoke($obj,array('doctor_rank_score'=>'84.5','qualification_score'=>0.72));
    $ok(is_array($good) && abs($good['doctor_rank_score']-84.5)<0.0001 && abs($good['qualification_score']-0.72)<0.0001, 'Normal bounded numeric ranking metadata preserved');

    foreach (array(INF, -INF, NAN, 'INF', 'NaN', '', 'invalid', array(12)) as $value) {
        $ok($numeric->invoke($obj,$value)===null, 'Non-finite/non-numeric source score rejected');
    }
    $ok($numeric->invoke($obj,'0.75')===0.75, 'Decimal score accepted without type coercion ambiguity');
    $ok($numeric->invoke($obj,0)===0.0, 'Zero score accepted');
    $ok($strict->invoke($obj,'00010',0)===10, 'Existing version/count integer parser unchanged');

    echo "PASS: File26 numeric payload and ranking-integrity source guard\n";
}
