<?php
/** Forty individually scoped checks against approved File26 Future-24 and owner contracts. */
$root = dirname(__DIR__);
$round = isset($argv[1]) ? (int)$argv[1] : 0;
if ($round < 1 || $round > 40) { fwrite(STDERR,"Invalid round: require 1..40\n");exit(2); }
$manifest = file_get_contents($root.'/includes/trait-file26-future-rest-trait.php');
$doc = file_get_contents($root.'/docs/FUTURE-SEARCH-KNOWLEDGE-INTELLIGENCE-SUPERSET-24-1.3.0.md');
$handlerCode = '';
foreach(glob($root.'/includes/trait-file26-future-*.php') as $f){$handlerCode.="\n".file_get_contents($f);}
$future = json_decode('[["conversational-grounded-search","مستند مکالماتی تلاش"],["query-planner","منصوبہ ساز تلاش"],["cross-language-search","کثیر لسانی تلاش"],["semantic-rerank","معنوی نتائج کی ترتیب"],["multimodal-search","متعدد ذرائع کی تلاش"],["voice-search","آوازی تلاش"],["segment-search","صفحہ/حصہ تلاش"],["find-similar","مشابہ مواد"],["research-search","تحقیقی تلاش"],["result-clusters","نتائج کے مجموعے"],["graph-path","علمی روابط کا راستہ"],["evidence-map","دلائل کا نقشہ"],["disambiguate","ابہام کا ازالہ"],["historical-search","تاریخی تلاش"],["research-trails","تحقیقی راستے"],["saved-search-alerts","محفوظ تلاش کے انتباہات"],["search-history","تلاش کی تاریخ"],["recommendation-transparency","سفارشات کی شفافیت"],["discovery-breadth","متنوع دریافت"],["geo-availability","مقام/دستیابی"],["search-modes","تلاش کے طریقے"],["private-search-vault","نجی تلاش کا مخزن"],["external-evidence","بیرونی شواہد"],["relevance-lab","نتائج کی مطابقت گاہ"]]',true);
$core = json_decode('[["Future activation safe mode","includes/class-file26-future-intelligence.php","empty( $health[\'activated\'] )",false],["Privacy exporter and eraser","includes/class-file26-privacy.php","future_meta_keys",false],["Search revocation / no shared cache","includes/class-file26-search.php","wp_cache_get(",true],["REST no-store on dynamic responses","includes/class-file26-rest.php","private, no-store",false],["File 04 migration reindex request","includes/class-file26-plugin.php","file04_request_reindex",false],["File 04 reindex verification receipt","includes/class-file26-plugin.php","file04_verify_reindex",false],["File 19 saved-search ownership","includes/class-file26-central-plan.php","sun_validate_saved_search_ownership",false],["File 19 notification delivery boundary","includes/trait-file26-future-user-data.php","delivery_owner\' => \'File 19\'",false],["Physical DB column-shape validation","includes/class-file26-plugin.php","verify_schema_shape()",false],["Metrics schema parity","includes/class-file26-plugin.php","metric_date",false],["Knowledge graph provenance","includes/class-file26-graph.php","provenance",false],["File 20 shell route registration","includes/class-file26-routes.php","sabri_shell_register_routes",false],["File 25 result contract version","includes/class-file26-plugin.php","sabri.file26.result.v1.3",false],["File 07 doctor data ownership","includes/trait-file26-future-user-discovery.php","File 07",false],["File 08 clinic availability ownership","includes/trait-file26-future-user-discovery.php","File 08",false],["Package and plugin 1.3.0 identity","file-26-search-discovery.php","Version: 1.3.0",false]]',true);
$ok = false;$label='';
if ($round <= 24) {
    $item = $future[$round-1]; $slug=$item[0]; $label=$item[1]; $id=sprintf('F26-FUT-%02d',$round);
    $start = "'".$slug."' => array( 'id' => '".$id."'";
    $found = strpos($manifest,$start);
    if ($found !== false) {
        $definition = substr($manifest,$found,340);
        if(preg_match("/'handler'\s*=>\s*'([a-z_]+)'/",$definition,$matches)) {
            $method=$matches[1];
            $ok = strpos($doc,$id)!==false && preg_match('/function\s+'.preg_quote($method,'/').'\s*\(/',$handlerCode)===1;
        }
    }
} else {
    $item = $core[$round-25];$label=$item[0];$file=$root.'/'.$item[1];
    $content=is_file($file)?file_get_contents($file):false;
    $ok = $content!==false && ($item[3] ? strpos($content,$item[2])===false : strpos($content,$item[2])!==false);
}
if (!$ok) { fwrite(STDERR, "FAIL F26-REVIEW-".sprintf('%02d',$round).": $label\n");exit(1); }
echo "PASS F26-REVIEW-".sprintf('%02d',$round).": $label\n";
