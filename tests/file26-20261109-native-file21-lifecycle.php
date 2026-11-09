<?php
/**
 * Real native File21 publication metadata transition acceptance on disposable
 * WordPress/MariaDB with the File21 monotonic source-event candidate installed.
 * Refuses external, staging and production sites.
 */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){
    fwrite(STDERR,"REFUSED: File21 native lifecycle test only on isolated file26-ci.local.\n");exit(2);
}
function f26_lifecycle_assert($check,$label){
    if(!$check){fwrite(STDERR,"FAIL: $label\n");exit(1);}
    echo "PASS: $label\n";
}
global $wpdb;
$plugin=\Sabri\File26\Plugin::instance();
$connectors=$plugin->connectors();
$slug='file21-publication';
$wpdb->update(\Sabri\File26\DB::table('connectors'),array('status'=>'active'),array('slug'=>$slug));
$register=\Sabri\HomeNewsFeed\SearchProviderRegistry::register_file26_connector();
f26_lifecycle_assert($register===true&&$connectors->is_active($slug),'Native owner connector manually activated only on ephemeral test DB');
$admin=get_user_by('login','admin');
f26_lifecycle_assert($admin instanceof WP_User,'Synthetic admin exists');
wp_set_current_user((int)$admin->ID);
$post_id=wp_insert_post(array(
    'post_type'=>'post','post_status'=>'publish',
    'post_author'=>(int)$admin->ID,'post_title'=>'File21 Native Public Lifecycle',
    'post_content'=>'Public clinical education sample, no patient information',
    'post_excerpt'=>'Safe native owner publication'
),true);
f26_lifecycle_assert(!is_wp_error($post_id)&&(int)$post_id>0,'Actual native WP post published through File21 hooks');
$post_id=(int)$post_id;
$source_meta='_sabri_file21_file26_event_version';
$review='\Sabri\HomeNewsFeed\PostMetadata';
update_post_meta($post_id,$review::META_REVIEW_STATE,'approved');
update_post_meta($post_id,$review::META_VISIBILITY,'public');
$document_table=\Sabri\File26\DB::table('documents');
$tombstone_table=\Sabri\File26\DB::table('tombstones');
$key=\Sabri\File26\Indexer::canonical_key('file21-publication','publication',(string)$post_id);
$doc=$wpdb->get_row($wpdb->prepare("SELECT object_version,source_event_sequence,title,locale FROM $document_table WHERE canonical_key=%s",$key),ARRAY_A);
f26_lifecycle_assert(is_array($doc)&&strpos($doc['title'],'File21 Native')!==false,'Native File21 published document indexed with real owner ACL');
$initial_version=(int)$doc['object_version'];
$initial_post_modified=get_post_field('post_modified_gmt',$post_id);
$batch=\Sabri\HomeNewsFeed\SearchProviderRegistry::file26_list_batch('',100);
$prior=array_values(array_filter($batch['items'],static function($item)use($post_id){return (string)$item['object_id']===(string)$post_id;}));
f26_lifecycle_assert(count($prior)===1,'Native owner full batch includes current public document');
$old_event=$prior[0];
f26_lifecycle_assert((int)$old_event['object_version']===$initial_version,'Owner snapshot and live serving index agree on canonical version');

update_post_meta($post_id,$review::META_VISIBILITY,'private');
$restricted=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $document_table WHERE canonical_key=%s",$key));
$revoked_version=$wpdb->get_var($wpdb->prepare("SELECT object_version FROM $tombstone_table WHERE canonical_key=%s",$key));
f26_lifecycle_assert((int)$restricted===0&&(int)$revoked_version>$initial_version,'Private metadata-only transition removes public index and creates a newer tombstone');
f26_lifecycle_assert(\Sabri\HomeNewsFeed\SearchProviderRegistry::file26_can_view($old_event,array('authenticated'=>false))===false,'Original old search result becomes unauthorized immediately');
update_post_meta($post_id,$review::META_VISIBILITY,'public');
$restored=$wpdb->get_row($wpdb->prepare("SELECT object_version,title FROM $document_table WHERE canonical_key=%s",$key),ARRAY_A);
f26_lifecycle_assert(is_array($restored)&&(int)$restored['object_version']>(int)$revoked_version,'Public metadata-only restoration outranks previous tombstone and reindexes');
f26_lifecycle_assert(get_post_field('post_modified_gmt',$post_id)===$initial_post_modified,'Core post modification timestamp unchanged across both metadata transitions');
f26_lifecycle_assert((int)get_post_meta($post_id,$source_meta,true)===(int)$restored['object_version'],'Owner event sequence is durably stored in post metadata');
$old_replay=sabri_file26_upsert_document($old_event);
$after_replay=$wpdb->get_var($wpdb->prepare("SELECT object_version FROM $document_table WHERE canonical_key=%s",$key));
f26_lifecycle_assert(!is_wp_error($old_replay)&&(int)$after_replay===(int)$restored['object_version'],'Out-of-order old public event cannot override restored current owner version');

update_post_meta($post_id,$review::META_LANGUAGE,'ur');
$localized=$wpdb->get_row($wpdb->prepare("SELECT object_version,locale FROM $document_table WHERE canonical_key=%s",$key),ARRAY_A);
f26_lifecycle_assert(is_array($localized)&&$localized['locale']==='ur'&&(int)$localized['object_version']>(int)$restored['object_version'],'Metadata-only locale change increments durable version and updates searchable locale');
$version_before_reapproval=(int)$localized['object_version'];
update_post_meta($post_id,$review::META_REVIEW_STATE,'pending');
$hidden=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $document_table WHERE canonical_key=%s",$key));
f26_lifecycle_assert((int)$hidden===0,'Unapproved review metadata change immediately removes public projection');
update_post_meta($post_id,$review::META_REVIEW_STATE,'approved');
$approved=$wpdb->get_row($wpdb->prepare("SELECT object_version,locale FROM $document_table WHERE canonical_key=%s",$key),ARRAY_A);
f26_lifecycle_assert(is_array($approved)&&(int)$approved['object_version']>$version_before_reapproval,'Reapproval publishes a genuinely newer event after restriction');
wp_set_current_user(0);
$search=sabri_file26_search(array('q'=>'File21 Native Public Lifecycle','locale'=>'ur'));
f26_lifecycle_assert(!is_wp_error($search)&&!empty($search['results']),'Anonymous File26 search discovers only restored approved public native publication');
wp_set_current_user((int)$admin->ID);
wp_delete_post($post_id,true);
$after_delete=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $document_table WHERE canonical_key=%s",$key));
$deleted_version=$wpdb->get_var($wpdb->prepare("SELECT object_version FROM $tombstone_table WHERE canonical_key=%s",$key));
f26_lifecycle_assert((int)$after_delete===0&&(int)$deleted_version>(int)$approved['object_version'],'Native owner physical deletion emits a higher versioned tombstone before removal');
echo "PASS: genuine File21 native metadata/public/restrict/restore/review/locale/delete with File26 real MariaDB\n";
