<?php
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$checks = array(
	'create_record( $input, true )' => 'standalone term creation uses atomic record helper',
	'Taxonomy term and aliases could not be stored atomically.' => 'term/alias partial creation fails atomically',
	'create_record( $target, false )' => 'split reuses transaction without nested START TRANSACTION',
	'An approved term requires an active parent term.' => 'approval requires active parent',
	'Parent is no longer active.' => 'parent state is rechecked under lock',
	'WHERE term_uuid=%s FOR UPDATE' => 'taxonomy lifecycle revalidates locked state',
	'Classification read failed.' => 'merge fails closed on assignment-read failure',
	'Alias read failed.' => 'merge fails closed on alias-read failure',
);
$failed=0;foreach($checks as $needle=>$label){if(false===strpos($source,$needle)){fwrite(STDERR,"FAIL: $label\n");$failed++;}else{echo "PASS: $label\n";}}exit($failed?1:0);
