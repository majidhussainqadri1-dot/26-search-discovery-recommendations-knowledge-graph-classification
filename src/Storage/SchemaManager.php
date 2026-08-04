<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class SchemaManager
{
    public const SCHEMA_VERSION = '1.0.0';

    /** @return list<string> */
    public static function tableSuffixes(): array
    {
        return [
            'generations', 'aliases', 'documents', 'tombstones', 'checkpoints', 'jobs', 'locks',
            'feedback', 'taxonomy_terms', 'graph_edges', 'classifications', 'evaluation_sets',
            'export_tokens', 'policies', 'telemetry_daily', 'audit_log', 'change_events',
            'owner_sequences', 'purge_ledger',
        ];
    }

    public static function install(wpdb $db): void
    {
        if (! function_exists('dbDelta') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if (! function_exists('dbDelta')) {
            throw new InvariantViolation('WordPress database migration support is unavailable.');
        }

        $charset = $db->get_charset_collate();
        $prefix = $db->prefix . 's26_';

        $sql = [
            "CREATE TABLE {$prefix}generations (
                generation_id varchar(64) NOT NULL,
                mode varchar(16) NOT NULL,
                state varchar(24) NOT NULL,
                created_at datetime(6) NOT NULL,
                validated_at datetime(6) NULL,
                promoted_at datetime(6) NULL,
                previous_generation_id varchar(64) NULL,
                document_count bigint unsigned NOT NULL DEFAULT 0,
                tombstone_count bigint unsigned NOT NULL DEFAULT 0,
                checksum char(64) NOT NULL DEFAULT '',
                PRIMARY KEY  (generation_id),
                KEY state_created (state, created_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}aliases (
                alias_key varchar(32) NOT NULL,
                generation_id varchar(64) NOT NULL,
                previous_generation_id varchar(64) NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (alias_key),
                KEY generation_id (generation_id)
            ) {$charset};",
            "CREATE TABLE {$prefix}documents (
                generation_id varchar(64) NOT NULL,
                canonical_key varchar(292) NOT NULL,
                connector_key varchar(100) NOT NULL,
                source_event_at datetime(6) NOT NULL,
                payload longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                PRIMARY KEY  (generation_id, canonical_key),
                KEY connector_event (generation_id, connector_key, source_event_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}tombstones (
                generation_id varchar(64) NOT NULL,
                canonical_key varchar(292) NOT NULL,
                connector_key varchar(100) NOT NULL,
                received_at datetime(6) NOT NULL,
                payload longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                PRIMARY KEY  (generation_id, canonical_key),
                KEY connector_received (generation_id, connector_key, received_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}checkpoints (
                generation_id varchar(64) NOT NULL,
                connector_key varchar(100) NOT NULL,
                cursor_value text NULL,
                is_complete tinyint(1) NOT NULL DEFAULT 0,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (generation_id, connector_key)
            ) {$charset};",
            "CREATE TABLE {$prefix}jobs (
                job_id char(64) NOT NULL,
                generation_id varchar(64) NOT NULL,
                connector_key varchar(100) NOT NULL,
                cursor_value text NULL,
                mode varchar(16) NOT NULL,
                attempt smallint unsigned NOT NULL DEFAULT 0,
                available_at datetime(6) NOT NULL,
                status varchar(24) NOT NULL,
                lease_expires_at datetime(6) NULL,
                error_code varchar(100) NULL,
                replay_count smallint unsigned NOT NULL DEFAULT 0,
                last_replayed_at datetime(6) NULL,
                created_at datetime(6) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (job_id),
                KEY claim_queue (status, available_at),
                KEY generation_connector (generation_id, connector_key)
            ) {$charset};",
            "CREATE TABLE {$prefix}locks (
                lock_key varchar(191) NOT NULL,
                token char(64) NOT NULL,
                expires_at datetime(6) NOT NULL,
                PRIMARY KEY  (lock_key),
                KEY expires_at (expires_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}feedback (
                feedback_id char(64) NOT NULL,
                actor_hash char(64) NOT NULL,
                target_key varchar(292) NOT NULL,
                feedback_type varchar(32) NOT NULL,
                state varchar(16) NOT NULL,
                context_hash char(64) NOT NULL,
                created_at datetime(6) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (feedback_id),
                KEY actor_state (actor_hash, state, updated_at),
                KEY target_type (target_key(191), feedback_type)
            ) {$charset};",
            "CREATE TABLE {$prefix}taxonomy_terms (
                term_id varchar(100) NOT NULL,
                version bigint unsigned NOT NULL,
                state varchar(24) NOT NULL,
                owner_key varchar(100) NOT NULL,
                redirect_term_id varchar(100) NULL,
                payload longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (term_id),
                KEY state_owner (state, owner_key),
                KEY redirect_term_id (redirect_term_id)
            ) {$charset};",
            "CREATE TABLE {$prefix}graph_edges (
                edge_id char(64) NOT NULL,
                from_key varchar(292) NOT NULL,
                to_key varchar(292) NOT NULL,
                edge_type varchar(64) NOT NULL,
                state varchar(24) NOT NULL,
                source_owner varchar(100) NOT NULL,
                source_version varchar(100) NOT NULL,
                payload longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (edge_id),
                KEY from_state (from_key(191), state, edge_type),
                KEY to_state (to_key(191), state)
            ) {$charset};",
            "CREATE TABLE {$prefix}classifications (
                classification_id char(64) NOT NULL,
                canonical_key varchar(292) NOT NULL,
                term_id varchar(100) NOT NULL,
                status varchar(24) NOT NULL,
                confidence decimal(6,5) NOT NULL,
                high_impact tinyint(1) NOT NULL DEFAULT 0,
                proposer_key varchar(100) NOT NULL,
                reviewer_key varchar(100) NULL,
                evidence_version varchar(100) NOT NULL,
                reason_code varchar(100) NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (classification_id),
                KEY object_status (canonical_key(191), status, updated_at),
                KEY term_status (term_id, status)
            ) {$charset};",
            "CREATE TABLE {$prefix}evaluation_sets (
                set_key varchar(100) NOT NULL,
                version varchar(32) NOT NULL,
                state varchar(24) NOT NULL,
                reviewer_key varchar(100) NOT NULL,
                payload longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (set_key, version),
                KEY state_updated (state, updated_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}export_tokens (
                token_hash char(64) NOT NULL,
                actor_id bigint unsigned NOT NULL,
                scopes_payload text NOT NULL,
                expires_at datetime(6) NOT NULL,
                used_at datetime(6) NULL,
                created_at datetime(6) NOT NULL,
                PRIMARY KEY  (token_hash),
                KEY actor_created (actor_id, created_at),
                KEY expires_used (expires_at, used_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}policies (
                policy_key varchar(100) NOT NULL,
                version varchar(32) NOT NULL,
                state varchar(24) NOT NULL,
                high_risk tinyint(1) NOT NULL DEFAULT 0,
                author_key varchar(100) NOT NULL,
                approvers_payload text NOT NULL,
                previous_version varchar(32) NULL,
                payload longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                effective_at datetime(6) NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (policy_key, version),
                KEY policy_state (policy_key, state, updated_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}telemetry_daily (
                metric_day date NOT NULL,
                metric_key varchar(100) NOT NULL,
                dimension_hash char(64) NOT NULL,
                dimensions_payload text NOT NULL,
                total bigint unsigned NOT NULL DEFAULT 0,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (metric_day, metric_key, dimension_hash),
                KEY metric_day_total (metric_key, metric_day, total)
            ) {$charset};",
            "CREATE TABLE {$prefix}audit_log (
                audit_id char(64) NOT NULL,
                actor_id bigint unsigned NOT NULL,
                action_key varchar(100) NOT NULL,
                object_key varchar(292) NOT NULL,
                reason_code varchar(100) NOT NULL,
                policy_version varchar(32) NULL,
                payload_hash char(64) NOT NULL,
                trace_id char(32) NOT NULL,
                created_at datetime(6) NOT NULL,
                PRIMARY KEY  (audit_id),
                KEY actor_created (actor_id, created_at),
                KEY object_created (object_key(191), created_at),
                UNIQUE KEY trace_id (trace_id)
            ) {$charset};",
            "CREATE TABLE {$prefix}change_events (
                event_id char(64) NOT NULL,
                idempotency_key varchar(191) NOT NULL,
                owner_key varchar(100) NOT NULL,
                canonical_key varchar(292) NOT NULL,
                object_version varchar(64) NOT NULL,
                event_type varchar(32) NOT NULL,
                occurred_at datetime(6) NOT NULL,
                sequence_number bigint unsigned NOT NULL,
                payload longtext NULL,
                payload_hash char(64) NULL,
                status varchar(24) NOT NULL,
                attempts smallint unsigned NOT NULL DEFAULT 0,
                error_code varchar(100) NULL,
                processed_at datetime(6) NULL,
                created_at datetime(6) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (event_id),
                UNIQUE KEY idempotency_key (idempotency_key),
                UNIQUE KEY owner_sequence (owner_key, sequence_number),
                KEY claim_events (status, updated_at, created_at),
                KEY canonical_event (canonical_key(191), occurred_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}owner_sequences (
                owner_key varchar(100) NOT NULL,
                last_sequence bigint unsigned NOT NULL,
                last_event_id char(64) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY  (owner_key)
            ) {$charset};",
            "CREATE TABLE {$prefix}purge_ledger (
                purge_id char(64) NOT NULL,
                owner_key varchar(100) NOT NULL,
                canonical_key varchar(292) NOT NULL,
                object_version varchar(64) NOT NULL,
                reason_code varchar(100) NOT NULL,
                requested_at datetime(6) NOT NULL,
                completed_at datetime(6) NULL,
                verified_absent_at datetime(6) NULL,
                trace_id char(32) NOT NULL,
                PRIMARY KEY  (purge_id),
                UNIQUE KEY trace_id (trace_id),
                KEY pending_purge (verified_absent_at, requested_at),
                KEY canonical_purge (canonical_key(191), requested_at)
            ) {$charset};",
        ];

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        foreach (self::tableSuffixes() as $suffix) {
            $expectedTable = $prefix . $suffix;
            $actualTable = $db->get_var($db->prepare('SHOW TABLES LIKE %s', $db->esc_like($expectedTable)));
            if ($actualTable !== $expectedTable) {
                throw new InvariantViolation('File 26 schema installation is incomplete: ' . $suffix);
            }
        }

        $requiredColumns = [
            'jobs' => ['replay_count', 'last_replayed_at'],
            'documents' => ['source_event_at', 'payload_hash'],
            'change_events' => ['idempotency_key', 'sequence_number', 'payload_hash', 'status'],
            'purge_ledger' => ['completed_at', 'verified_absent_at', 'trace_id'],
        ];
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                $actualColumn = $db->get_var($db->prepare(
                    "SHOW COLUMNS FROM {$prefix}{$table} LIKE %s",
                    $column
                ));
                if ($actualColumn !== $column) {
                    throw new InvariantViolation('File 26 schema column is missing: ' . $table . '.' . $column);
                }
            }
        }

        update_option('sabri_file26_schema_version', self::SCHEMA_VERSION, false);
    }
}
