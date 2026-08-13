<?php
$root=dirname(__DIR__);$source=file_get_contents($root.'/includes/class-file26-rest.php');$checks=array(
'rest_pre_dispatch'=>'cross-cutting account membership gate is installed',
'saved-queries(?:/|$)'=>'central saved-query routes are covered by membership gate',
'content-gap$'=>'central content-gap route is covered by membership gate',
'file26_membership_invalid'=>'invalid or suspended membership fails closed',
"empty(\$filters['availability'])"=>'availability search is not HTTP-public-cached',
'$public_term=array('=>'topic response uses minimized public projection',
'is_wp_error($appeals)?$appeals'=>'own-appeal errors are not wrapped in a false 200 response',
'$result=$this->indexer->reconcile();return is_wp_error($result)?$result'=>'reconcile REST truth propagates proven operation failure'
);$failed=0;foreach($checks as $needle=>$label){if(false===strpos($source,$needle)){fwrite(STDERR,"FAIL: $label\n");$failed++;}else{echo "PASS: $label\n";}}exit($failed?1:0);
