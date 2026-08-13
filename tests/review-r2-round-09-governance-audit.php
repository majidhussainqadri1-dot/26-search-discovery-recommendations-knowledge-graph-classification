<?php
$root=dirname(__DIR__);$source=file_get_contents($root.'/includes/class-file26-governance.php');$checks=array(
'Required governance audit could not be persisted.'=>'high-risk mutation treats audit persistence as mandatory',
'WHERE slug=%s FOR UPDATE'=>'connector lifecycle revalidates under lock',
'policy_version\' => $row[\'version\']'=>'rollback approval binds exact active policy version',
'effective_at\' => $row[\'effective_at\']'=>'rollback approval binds exact activation generation',
"delete_transient( \$receipt_key )"=>'rollback approval is consumed before mutation',
'false !== get_transient( $receipt_key )'=>'rollback receipt consumption is verified',
'Ranking policy setting did not persist.'=>'runtime policy-setting projection is verified',
'WHERE object_key=%s AND term_uuid=%s FOR UPDATE'=>'classification review locks exact assignment',
'Classification decision or required audit could not be committed.'=>'classification and audit share failure boundary',
'Graph edge correction or required audit could not be committed.'=>'graph correction and audit share failure boundary'
);$failed=0;foreach($checks as $needle=>$label){if(false===strpos($source,$needle)){fwrite(STDERR,"FAIL: $label\n");$failed++;}else{echo "PASS: $label\n";}}exit($failed?1:0);
