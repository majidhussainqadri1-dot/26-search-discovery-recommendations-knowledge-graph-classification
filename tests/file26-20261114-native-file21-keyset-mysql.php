<?php
/**
 * Genuine WordPress/MariaDB File21 owner ID-keyset acceptance, disposable ONLY.
 * Tests owner pagination against publication withdrawal between exact pages.
 * Does NOT claim File26 full canonical reindex or Hostinger deployment.
 */
if ('yes' !== getenv('FILE26_CI_EPHEMERAL_DB') || false === strpos(home_url('/'), 'file26-ci.local')) {
    fwrite(STDERR, "REFUSED: File21 keyset SQL test is for disposable file26-ci.local only.\n");
    exit(2);
}
function file26_keyset_assert($condition,$label) {
    if (!$condition) { fwrite(STDERR, "FAIL: ".$label."\n"); exit(1); }
    echo "PASS: ".$label."\n";
}
$owner = '\\Sabri\\HomeNewsFeed\\SearchProviderRegistry';
file26_keyset_assert(class_exists($owner),'Actual native File21 owner plugin installed');
$admin = get_user_by('login','admin');
file26_keyset_assert($admin instanceof WP_User,'Disposable WordPress admin is available');
wp_set_current_user((int)$admin->ID);
$review = '\\Sabri\\HomeNewsFeed\\PostMetadata';
$ids = array();
for ($i=1;$i<=4;$i++) {
    $id=wp_insert_post(array(
        'post_type'=>'post',
        'post_status'=>'publish',
        'post_author'=>(int)$admin->ID,
        'post_title'=>'File21 Keyset Real SQL '.$i,
        'post_content'=>'Safe educational test publication '.$i,
        'post_excerpt'=>'Source-only public placeholder '.$i
    ),true);
    file26_keyset_assert(!is_wp_error($id) && (int)$id>0,'Public fixture #'.$i.' inserted by actual native WordPress hooks');
    $id=(int)$id;
    update_post_meta($id,$review::META_REVIEW_STATE,'approved');
    update_post_meta($id,$review::META_VISIBILITY,'public');
    $ids[]=$id;
}
file26_keyset_assert($ids[0] < $ids[1] && $ids[1] < $ids[2] && $ids[2] < $ids[3],'Autoincrement owner IDs have strict ordering');
$start = $ids[0]>1 ? 'f21-id-v1:'.($ids[0]-1) : '';
$page1=$owner::file26_list_batch($start,2);
file26_keyset_assert(is_array($page1) && $page1['done']===false,'Native owner first full page is nonfinal');
$first_ids=array_map('intval',array_column($page1['items'],'object_id'));
file26_keyset_assert($first_ids===array_slice($ids,0,2),'First SQL page contains exact first two owner post IDs');
file26_keyset_assert($page1['next_cursor']==='f21-id-v1:'.$ids[1],'Source returned versioned keyset cursor, not offset');
// Revoke first owner publication between pages, shrinking the eligible source.
wp_update_post(array('ID'=>$ids[0], 'post_status'=>'draft'));
file26_keyset_assert('draft'===get_post_status($ids[0]),'First owner post leaves published query set between pages');
$page2=$owner::file26_list_batch($page1['next_cursor'],2);
file26_keyset_assert(is_array($page2),'Second native owner page is readable after revocation');
$second_ids=array_map('intval',array_column($page2['items'],'object_id'));
file26_keyset_assert($second_ids===array_slice($ids,2,2),'Post-ID keyset does not skip third post when first is withdrawn');
$old=$owner::file26_list_batch('2',2);
file26_keyset_assert(is_wp_error($old) && $old->get_error_code()==='file21_file26_cursor_incompatible','Old numeric offset cursor fails closed and requires new shadow generation');
$final=$owner::file26_list_batch($page2['next_cursor'],2);
file26_keyset_assert(is_array($final) && $final['done']===true,'Empty last SQL page proves bounded source traversal complete');
foreach ($ids as $id) { wp_delete_post($id,true); }
echo "PASS: actual native File21 source ID keyset over WordPress/MariaDB under mid-scan withdrawal\n";
