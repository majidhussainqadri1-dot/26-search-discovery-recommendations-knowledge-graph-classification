<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Institutional separation-of-duties role model. */
final class Roles {
	const OPTION_VERSION = 'sabri_file26_role_model_version';
	const VERSION = '1.1.0';
	private static $operational_caps = array( 'operate_sabri_search', 'curate_sabri_taxonomy', 'approve_sabri_ranking', 'audit_sabri_search' );

	public static function install( $force = false ) {
		if ( ! $force && self::VERSION === get_option( self::OPTION_VERSION ) ) {
			$verified = self::verify();
			if ( true === $verified ) { return true; }
		}

		$administrator = get_role( 'administrator' );
		if ( ! $administrator ) { return new \WP_Error( 'file26_admin_role_missing', 'WordPress administrator role is unavailable.' ); }
		$administrator->add_cap( 'manage_sabri_search' );
		foreach ( self::$operational_caps as $cap ) { $administrator->remove_cap( $cap ); }

		$definitions = array(
			'sabri_search_operator' => array( __( 'Sabri Search Operator', 'sabri-file26' ), 'operate_sabri_search' ),
			'sabri_taxonomy_curator' => array( __( 'Sabri Taxonomy Curator', 'sabri-file26' ), 'curate_sabri_taxonomy' ),
			'sabri_ranking_approver' => array( __( 'Sabri Ranking Approver', 'sabri-file26' ), 'approve_sabri_ranking' ),
			'sabri_search_auditor' => array( __( 'Sabri Search Auditor', 'sabri-file26' ), 'audit_sabri_search' ),
		);
		foreach ( $definitions as $slug => $definition ) {
			$result = self::ensure_role( $slug, $definition[0], $definition[1] );
			if ( is_wp_error( $result ) ) { return $result; }
		}
		$verified = self::verify();
		if ( is_wp_error( $verified ) ) { return $verified; }
		$written = update_option( self::OPTION_VERSION, self::VERSION, false );
		if ( ! $written && self::VERSION !== get_option( self::OPTION_VERSION ) ) {
			return new \WP_Error( 'file26_role_version_write_failed', 'File 26 role-model version could not be persisted.' );
		}
		return true;
	}

	private static function ensure_role( $slug, $label, $granted_cap ) {
		$role = get_role( $slug );
		if ( ! $role ) { add_role( $slug, $label, array( 'read' => true, $granted_cap => true ) ); $role = get_role( $slug ); }
		if ( ! $role ) { return new \WP_Error( 'file26_role_create_failed', 'A required File 26 separation-of-duties role could not be created.' ); }
		$role->add_cap( 'read' );
		$role->remove_cap( 'manage_sabri_search' );
		foreach ( self::$operational_caps as $cap ) {
			if ( $cap === $granted_cap ) { $role->add_cap( $cap ); } else { $role->remove_cap( $cap ); }
		}
		return true;
	}

	public static function verify() {
		$administrator = get_role( 'administrator' );
		if ( ! $administrator || ! $administrator->has_cap( 'manage_sabri_search' ) ) {
			return new \WP_Error( 'file26_role_model_incomplete', 'Administrator configuration capability is missing.' );
		}
		foreach ( self::$operational_caps as $cap ) {
			if ( $administrator->has_cap( $cap ) ) { return new \WP_Error( 'file26_role_model_privilege_overlap', 'Administrator unexpectedly has an operational File 26 capability.' ); }
		}
		$expected = array(
			'sabri_search_operator' => 'operate_sabri_search',
			'sabri_taxonomy_curator' => 'curate_sabri_taxonomy',
			'sabri_ranking_approver' => 'approve_sabri_ranking',
			'sabri_search_auditor' => 'audit_sabri_search',
		);
		foreach ( $expected as $slug => $granted ) {
			$role = get_role( $slug );
			if ( ! $role || ! $role->has_cap( 'read' ) || ! $role->has_cap( $granted ) || $role->has_cap( 'manage_sabri_search' ) ) {
				return new \WP_Error( 'file26_role_model_incomplete', 'A File 26 duty role is missing its bounded capability.' );
			}
			foreach ( self::$operational_caps as $cap ) {
				if ( $cap !== $granted && $role->has_cap( $cap ) ) { return new \WP_Error( 'file26_role_model_privilege_overlap', 'A File 26 duty role has an unauthorized sibling capability.' ); }
			}
		}
		return true;
	}
}
