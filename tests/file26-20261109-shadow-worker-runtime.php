<?php
/**
 * Standalone runtime regression for shadow worker leases.
 * Exercises real Shadow_Index::stage control flow with a deterministic WordPress DB stub.
 * Does NOT replace MySQL/WordPress staging integration acceptance.
 */
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('ARRAY_A', 'ARRAY_A');
    class WP_Error {
        private $code;
        public function __construct($code, $message = '') { $this->code = $code; }
        public function get_error_code() { return $this->code; }
    }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
    function get_option($key, $default = false) { return isset($GLOBALS['f26_options'][$key]) ? $GLOBALS['f26_options'][$key] : $default; }
    class F26FakeWpdb {
        public $prefix = 'wp_';
        public $last_error = '';
        public $lock_allowed = true;
        public $job = array('status' => 'running', 'lock_token' => 'valid-lease-token');
        public $releases = 0;
        public $writes = array();
        public $transactions = array();
        public function prepare($query) { return $query; }
        public function get_var($sql) {
            if (strpos($sql, 'GET_LOCK') !== false) { return $this->lock_allowed ? '1' : '0'; }
            if (strpos($sql, 'RELEASE_LOCK') !== false) { ++$this->releases; return '1'; }
            if (strpos($sql, 'SELECT object_version FROM') !== false) { return null; }
            throw new \RuntimeException('Unexpected get_var: ' . $sql);
        }
        public function get_row($sql, $output = null) {
            if (strpos($sql, "job_type='shadow_reindex'") !== false) { return $this->job; }
            if (strpos($sql, 'SELECT object_version,source_event_sequence,checksum') !== false) { return null; }
            throw new \RuntimeException('Unexpected get_row: ' . $sql);
        }
        public function query($sql) {
            if (in_array($sql, array('START TRANSACTION', 'COMMIT', 'ROLLBACK'), true)) {
                $this->transactions[] = $sql; return 1;
            }
            throw new \RuntimeException('Unexpected query: ' . $sql);
        }
        public function replace($table, $data) {
            $this->writes[] = array('action'=>'replace','table'=>$table);
            return 1;
        }
        public function delete($table, $where) {
            $this->writes[] = array('action'=>'delete','table'=>$table);
            return 1;
        }
    }
}
namespace Sabri\File26 {
    class DB {
        public static function table($name) { global $wpdb; return $wpdb->prefix . 'f26_' . $name; }
    }
    require_once dirname(__DIR__) . '/includes/class-file26-shadow-index.php';
}
namespace {
    use Sabri\File26\Shadow_Index;
    function verify($condition, $message) {
        if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
        echo "PASS: $message\n";
    }
    $uuid = '12345678-1234-4234-8234-123456789abc';
    $option = 'sabri_f26_shadow_' . str_replace('-', '', $uuid);
    $doc = array(
        'connector_slug'=>'file05','object_id'=>'article-17',
        'canonical_key'=>str_repeat('a', 64),'object_version'=>2,
        'source_event_sequence'=>2,'checksum'=>str_repeat('b', 64),
        'state'=>'published','visibility'=>'public','entity_type'=>'article',
        'canonical_url'=>'https://example.org/article-17','locale'=>'ur',
        'title'=>'Test','payload'=>'{}','updated_at'=>'2026-11-09 01:00:00'
    );
    $GLOBALS['f26_options'] = array($option=>array(
        'state'=>'prepared','connector'=>'file05','partial_object_id'=>''
    ));

    $wpdb = new F26FakeWpdb();
    $result = Shadow_Index::stage($uuid, $doc, 'expired-lease');
    verify(is_wp_error($result) && 'file26_shadow_stale_worker'===$result->get_error_code(), 'Expired worker is rejected');
    verify(count($wpdb->writes)===0 && $wpdb->releases===1, 'Expired worker makes no writes and releases cutover lock');

    $wpdb = new F26FakeWpdb();
    $wpdb->job['status'] = 'completed';
    $result = Shadow_Index::stage($uuid, $doc, 'valid-lease-token');
    verify(is_wp_error($result) && 'file26_shadow_stale_worker'===$result->get_error_code(), 'Completed queue job cannot stage after validation');
    verify(count($wpdb->writes)===0, 'Completed queue job makes no writes');

    $wpdb = new F26FakeWpdb();
    $wpdb->lock_allowed = false;
    $result = Shadow_Index::stage($uuid, $doc, 'valid-lease-token');
    verify(is_wp_error($result) && 'file26_shadow_busy'===$result->get_error_code(), 'Concurrent cutover denies staging');
    verify(count($wpdb->writes)===0 && $wpdb->releases===0, 'Denied cutover lock never writes or unlocks another session');

    $wpdb = new F26FakeWpdb();
    $GLOBALS['f26_options'][$option]['state'] = 'swapped';
    $result = Shadow_Index::stage($uuid, $doc, 'valid-lease-token');
    verify(is_wp_error($result) && 'file26_shadow_wrong_owner'===$result->get_error_code(), 'Stale generation cannot write following promotion');
    verify(count($wpdb->writes)===0 && $wpdb->releases===1, 'Promoted generation cannot mutate serving index');

    $wpdb = new F26FakeWpdb();
    $GLOBALS['f26_options'][$option]['state'] = 'prepared';
    $result = Shadow_Index::stage($uuid, $doc, 'valid-lease-token');
    verify($result===true && count($wpdb->writes)===2, 'Valid worker stages document and graph node together');
    verify(strpos($wpdb->writes[0]['table'], 'wp_f26_sd_')===0 && strpos($wpdb->writes[1]['table'], 'wp_f26_sn_')===0, 'Valid worker writes only physically separate shadow tables');
    verify($wpdb->transactions===array('START TRANSACTION','COMMIT') && $wpdb->releases===1, 'Successful staging commits then releases lock');

    $wpdb = new F26FakeWpdb();
    $deleted = $doc;
    $deleted['state'] = 'deleted';
    $result = Shadow_Index::stage($uuid, $deleted, 'valid-lease-token');
    verify($result===true && count($wpdb->writes)===2, 'Owner deletion only deletes candidate document and candidate node');
    verify($wpdb->writes[0]['action']==='delete' && $wpdb->writes[1]['action']==='delete' && $wpdb->releases===1, 'Deletion leaves serving index untouched and unlocks');
    echo "PASS: active shadow worker runtime contract\n";
}
