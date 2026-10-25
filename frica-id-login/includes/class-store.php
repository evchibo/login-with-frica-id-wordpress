<?php
namespace FricaID\WordPress;

if (!defined('ABSPATH')) { exit; }

// Custom authentication tables use uncached, prepared SQL so stale object caches cannot bypass uniqueness or single-use state.
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom tables have no core CRUD API; authentication state must be read atomically from the database.
final class Store {
    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'frica_id_' . $name;
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $collate = $wpdb->get_charset_collate();
        $links = self::table('links');
        $flows = self::table('flows');
        dbDelta("CREATE TABLE $links (
            user_id bigint(20) unsigned NOT NULL,
            subject_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (user_id),
            UNIQUE KEY subject_hash (subject_hash)
        ) $collate;");
        dbDelta("CREATE TABLE $flows (
            state_hash char(64) NOT NULL,
            browser_hash char(64) NOT NULL,
            payload longtext NOT NULL,
            expires_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (state_hash),
            KEY expires_at (expires_at)
        ) $collate;");
        update_option('frica_id_schema', '1', false);
    }

    public static function subject_key(string $subject): string {
        return hash('sha256', OIDC::ISSUER . "\0" . $subject);
    }

    public static function linked_user(string $subject): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT user_id FROM %i WHERE subject_hash = %s', self::table('links'), self::subject_key($subject)
        ));
    }

    public static function is_linked(int $user_id): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM %i WHERE user_id = %d', self::table('links'), $user_id));
    }

    public static function link(int $user_id, string $subject): bool {
        global $wpdb;
        // Unique constraints protect both sides, including concurrent callbacks.
        $previous = $wpdb->suppress_errors(true);
        $ok = $wpdb->insert(self::table('links'), [
            'user_id' => $user_id, 'subject_hash' => self::subject_key($subject), 'created_at' => current_time('mysql', true),
        ], ['%d', '%s', '%s']);
        $wpdb->suppress_errors($previous);
        return $ok === 1;
    }

    public static function unlink(int $user_id): void {
        global $wpdb;
        $wpdb->delete(self::table('links'), ['user_id' => $user_id], ['%d']);
    }

    public static function save_flow(string $state, string $browser, array $payload): bool {
        global $wpdb;
        self::cleanup();
        return $wpdb->insert(self::table('flows'), [
            'state_hash' => hash('sha256', $state), 'browser_hash' => hash('sha256', $browser),
            'payload' => wp_json_encode($payload), 'expires_at' => time() + 600,
        ], ['%s', '%s', '%s', '%d']) === 1;
    }

    public static function consume_flow(string $state, string $browser): ?array {
        global $wpdb;
        $hash = hash('sha256', $state);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE state_hash = %s', self::table('flows'), $hash), ARRAY_A);
        if (!$row || (int) $row['expires_at'] < time() || !hash_equals($row['browser_hash'], hash('sha256', $browser))) {
            return null;
        }
        // Exactly one request can consume this state, independent of object caching.
        $deleted = $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE state_hash = %s AND browser_hash = %s AND expires_at >= %d',
            self::table('flows'), $hash, hash('sha256', $browser), time()
        ));
        if ($deleted !== 1) { return null; }
        $payload = json_decode($row['payload'], true);
        return is_array($payload) ? $payload : null;
    }

    public static function cleanup(): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE expires_at < %d', self::table('flows'), time()));
    }
}
