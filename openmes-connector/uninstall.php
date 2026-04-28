<?php
/**
 * Uninstall handler — removes all plugin options.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('openmes_connector_settings');

global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key IN ('_openmes_manufacture', '_openmes_line_id')"
);
