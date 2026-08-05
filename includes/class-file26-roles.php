<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * Institutional separation-of-duties role model.
 * The administrator role configures File 26 but does not automatically inherit
 * operator, curator, ranking-approver or auditor powers.
 */
final class Roles {
	const OPTION_VERSION = 'sabri_file26_role_model_version';
	const VERSION = '1.1.0';

	public static function install( $force = false ) {
		if ( ! $force && self::VERSION === get_option( self::OPTION_VERSION ) ) {
			return;
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_sabri_search' );
			foreach ( array( 'operate_sabri_search', 'curate_sabri_taxonomy', 'approve_sabri_ranking', 'audit_sabri_search' ) as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}

		self::ensure_role( 'sabri_search_operator', __( 'Sabri Search Operator', 'sabri-file26' ), array(
			'read' => true,
			'operate_sabri_search' => true,
		) );
		self::ensure_role( 'sabri_taxonomy_curator', __( 'Sabri Taxonomy Curator', 'sabri-file26' ), array(
			'read' => true,
			'curate_sabri_taxonomy' => true,
		) );
		self::ensure_role( 'sabri_ranking_approver', __( 'Sabri Ranking Approver', 'sabri-file26' ), array(
			'read' => true,
			'approve_sabri_ranking' => true,
		) );
		self::ensure_role( 'sabri_search_auditor', __( 'Sabri Search Auditor', 'sabri-file26' ), array(
			'read' => true,
			'audit_sabri_search' => true,
		) );
		update_option( self::OPTION_VERSION, self::VERSION, false );
	}

	private static function ensure_role( $slug, $label, array $capabilities ) {
		$role = get_role( $slug );
		if ( ! $role ) {
			add_role( $slug, $label, $capabilities );
			$role = get_role( $slug );
		}
		if ( ! $role ) {
			return;
		}
		foreach ( array( 'manage_sabri_search', 'operate_sabri_search', 'curate_sabri_taxonomy', 'approve_sabri_ranking', 'audit_sabri_search' ) as $cap ) {
			if ( ! empty( $capabilities[ $cap ] ) ) {
				$role->add_cap( $cap );
			} else {
				$role->remove_cap( $cap );
			}
		}
		$role->add_cap( 'read' );
	}
}
