<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class SchemaUpgradeCoordinator
{
    private const LOCK_NAME = 'sabri_file26_schema_upgrade';

    public function __construct(private readonly wpdb $db)
    {
    }

    public static function supportsUpgradeFrom(string $version): bool
    {
        return in_array($version, ['0.1.0', '0.2.0', '0.3.0', '0.4.0'], true);
    }

    public function ensureCurrent(): void
    {
        $current = (string) get_option('sabri_file26_schema_version', '');
        if ($current === SchemaManager::SCHEMA_VERSION) {
            return;
        }
        if (! self::supportsUpgradeFrom($current)) {
            throw new InvariantViolation('File 26 schema requires an explicit supported upgrade path.');
        }

        $acquired = $this->db->get_var(
            $this->db->prepare('SELECT GET_LOCK(%s, %d)', self::LOCK_NAME, 5)
        );
        if ((string) $acquired !== '1') {
            throw new InvariantViolation('File 26 schema upgrade lock could not be acquired.');
        }

        try {
            $current = (string) get_option('sabri_file26_schema_version', '');
            if ($current === SchemaManager::SCHEMA_VERSION) {
                return;
            }
            if (! self::supportsUpgradeFrom($current)) {
                throw new InvariantViolation('File 26 schema changed while waiting for the upgrade lock.');
            }

            SchemaManager::install($this->db);
            if ((string) get_option('sabri_file26_schema_version', '') !== SchemaManager::SCHEMA_VERSION) {
                throw new InvariantViolation('File 26 schema upgrade did not reach the required version.');
            }
        } finally {
            $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', self::LOCK_NAME));
        }
    }
}
