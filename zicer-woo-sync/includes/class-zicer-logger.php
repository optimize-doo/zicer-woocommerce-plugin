<?php
/**
 * ZICER Logger
 *
 * Logging utility for sync operations.
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Zicer_Logger
 */
class Zicer_Logger {

    /**
     * Log a message
     *
     * @param string $level   Log level (info, error, warning).
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     */
    public static function log($level, $message, $context = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_log';

        $wpdb->insert($table, [
            'product_id' => isset($context['product_id']) ? $context['product_id'] : null,
            'listing_id' => isset($context['listing_id']) ? $context['listing_id'] : null,
            'action'     => isset($context['action']) ? $context['action'] : $level,
            'status'     => $level,
            'message'    => $message . (!empty($context) ? ' | ' . wp_json_encode($context) : ''),
            'created_at' => current_time('mysql'),
        ]);

        // Also log to WP debug if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[ZICER $level] $message");
        }
    }

    /**
     * Get log entries
     *
     * @param int $limit  Number of entries to return.
     * @param int $offset Offset for pagination.
     * @return array
     */
    public static function get_logs($limit = 100, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_log';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Get total log count
     *
     * @return int
     */
    public static function get_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_log';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_log';
        $wpdb->query("TRUNCATE TABLE $table");
    }
}
