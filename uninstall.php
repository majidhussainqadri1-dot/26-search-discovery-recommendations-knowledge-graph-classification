<?php
/**
 * File 26 non-destructive uninstall policy.
 *
 * Canonical and derivative data must never be purged merely because the plugin
 * is removed. A future guarded purge operation requires explicit authorization,
 * dependency checks, export evidence, and a deletion-reconciliation workflow.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Intentionally non-destructive.
