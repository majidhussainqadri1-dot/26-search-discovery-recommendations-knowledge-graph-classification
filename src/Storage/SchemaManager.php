<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use wpdb;

final class SchemaManager
{
    public const SCHEMA_VERSION = '0.3.0';

    public static function install(wpdb $db): void
    {
        if (! function_exists('dbDelta') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
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
                PRIMARY KEY  (alias_key)
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
        ];

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('sabri_file26_schema_version', self::SCHEMA_VERSION, false);
    }
}
