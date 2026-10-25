<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

// Per-site activation only. Remove plugin-owned records, never WordPress user accounts.
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Uninstall must drop only the plugin-owned tables; schema deletion has no cacheable result.
global $wpdb;
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'frica_id_flows'));
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'frica_id_links'));
delete_option('frica_id_settings');
delete_option('frica_id_schema');
delete_transient('frica_id_jwks');
wp_clear_scheduled_hook('frica_id_cleanup');
