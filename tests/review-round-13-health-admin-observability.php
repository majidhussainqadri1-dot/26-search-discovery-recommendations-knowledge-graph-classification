<?php
/** Round 13 regression: operational truth and admin writes cannot silently succeed. */
$root=dirname(__DIR__);
$health=file_get_contents($root.'/includes/class-file26-health.php');
$connectors=file_get_contents($root.'/includes/class-file26-connectors.php');
$admin=file_get_contents($root.'/includes/class-file26-admin.php');
$checks=array(
	array($health,'db_read_failure','health exposes DB-read uncertainty'),
	array($health,'oldest_pending_age_seconds','health includes queue lag evidence'),
	array($health,'operational_failures','health includes persistent failure markers'),
	array($health,'connector_boot','connector boot failures degrade health'),
	array($connectors,'sanitize_health_detail','connector health callback output is allowlisted'),
	array($connectors,'file26_connector_boot_incomplete','connector boot errors are explicit'),
	array($connectors,'sabri_file26_last_connector_health_failure','health persistence failure is recorded'),
	array($admin,'personalization_enable','enabling personalization requires step-up'),
	array($admin,'is_wp_error($updated)','settings write failure cannot redirect as success'),
	array($admin,'is_wp_error($audit)','required audit failure is not silently ignored'),
);
$failures=0;foreach($checks as $check){if(false===strpos($check[0],$check[1])){fwrite(STDERR,'FAIL: '.$check[2]."\n");$failures++;}}
if($failures){exit(1);}echo "Round 13 health/admin observability regression passed.\n";
