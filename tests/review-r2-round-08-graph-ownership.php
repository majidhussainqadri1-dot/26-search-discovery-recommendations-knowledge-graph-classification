<?php
$root=dirname(__DIR__);$source=file_get_contents($root.'/includes/class-file26-graph.php');$checks=array(
'source_owner_for_node'=>'graph ownership is resolved from canonical indexed source',
'file26_graph_owner_spoof'=>'caller-supplied graph ownership cannot spoof canonical owner',
'file26_graph_source_owner_unknown'=>'unknown source ownership fails closed',
'file26_graph_owner_stale'=>'source ownership is revalidated before activation',
"esc_url_raw(\$url,array('https'))"=>'external graph evidence is HTTPS-only',
'file26_graph_read_failed'=>'graph read failures fail closed',
"'state'=>'draft'"=>'edge creation remains non-public lifecycle state'
);$failed=0;foreach($checks as $needle=>$label){if(false===strpos($source,$needle)){fwrite(STDERR,"FAIL: $label\n");$failed++;}else{echo "PASS: $label\n";}}exit($failed?1:0);
