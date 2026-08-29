<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Institutional separation-of-duties role model. */
final class Roles {
	const OPTION_VERSION='sabri_file26_role_model_version'; const VERSION='1.1.0';
	public static function install($force=false){
		if(!$force&&self::VERSION===get_option(self::OPTION_VERSION)){return true;}
		$administrator=get_role('administrator');if(!$administrator){delete_option(self::OPTION_VERSION);return false;}
		$administrator->add_cap('manage_sabri_search');foreach(array('operate_sabri_search','curate_sabri_taxonomy','approve_sabri_ranking','audit_sabri_search') as $cap){$administrator->remove_cap($cap);}
		$roles=array(
			array('sabri_search_operator',__('Sabri Search Operator','sabri-file26'),array('read'=>true,'operate_sabri_search'=>true)),
			array('sabri_taxonomy_curator',__('Sabri Taxonomy Curator','sabri-file26'),array('read'=>true,'curate_sabri_taxonomy'=>true)),
			array('sabri_ranking_approver',__('Sabri Ranking Approver','sabri-file26'),array('read'=>true,'approve_sabri_ranking'=>true)),
			array('sabri_search_auditor',__('Sabri Search Auditor','sabri-file26'),array('read'=>true,'audit_sabri_search'=>true)),
		);
		foreach($roles as $definition){if(!self::ensure_role($definition[0],$definition[1],$definition[2])){delete_option(self::OPTION_VERSION);return false;}}
		$saved=update_option(self::OPTION_VERSION,self::VERSION,false);return $saved||self::VERSION===get_option(self::OPTION_VERSION);
	}
	private static function ensure_role($slug,$label,array $capabilities){$role=get_role($slug);if(!$role){add_role($slug,$label,$capabilities);$role=get_role($slug);}if(!$role){return false;}foreach(array('manage_sabri_search','operate_sabri_search','curate_sabri_taxonomy','approve_sabri_ranking','audit_sabri_search') as $cap){if(!empty($capabilities[$cap])){$role->add_cap($cap);}else{$role->remove_cap($cap);}}$role->add_cap('read');foreach($capabilities as $cap=>$required){if($required&&!$role->has_cap($cap)){return false;}}return true;}
}
