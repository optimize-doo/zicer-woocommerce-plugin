<?php
/**
 * ZICER Queue
 *
 * Background job queue for sync operations.
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Zicer_Queue
 */
class Zicer_Queue {

    /**
     * Maximum retry attempts
     */
    const MAX_ATTEMPTS = 3;

    /**
     * Initialize queue
     */
    public static function init() {
        add_action('zicer_process_queue', [__CLASS__, 'process']);
    }

    /**
     * Add item to queue
     *
     * @param int    $product_id The product ID.
     * @param string $action     The action (sync or delete).
     * @param array  $meta       Additional meta data.
     * @return int|false The queue item ID or false.
     */
    public static function add($product_id, $action, $meta = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        // Check for existing pending item with same action
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE product_id = %d AND action = %s AND status = 'pending'",
            $product_id,
            $action
        ));

        if ($existing) {
            return $existing;
        }

        // Remove any conflicting pending action (sync vs delete)
        $conflicting_action = ($action === 'sync') ? 'delete' : 'sync';
        $wpdb->delete($table, [
            'product_id' => $product_id,
            'action'     => $conflicting_action,
            'status'     => 'pending',
        ]);

        $wpdb->insert($table, [
            'product_id' => $product_id,
            'action'     => $action,
            'status'     => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        if (!empty($meta)) {
            update_post_meta($product_id, '_zicer_queue_meta', $meta);
        }

        return $wpdb->insert_id;
    }

    /**
     * Process queue items
     */
    public static function process() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        // Get pending items (limit to 10 per run for rate limiting)
        $items = $wpdb->get_results(
            "SELECT * FROM $table
             WHERE status = 'pending' AND attempts < " . self::MAX_ATTEMPTS . "
             ORDER BY created_at ASC
             LIMIT 10"
        );

        foreach ($items as $item) {
            self::process_item($item);
        }

        // Clean old completed items (older than 7 days)
        $wpdb->query(
            "DELETE FROM $table
             WHERE status = 'completed'
             AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    /**
     * Process a single queue item
     *
     * @param object $item The queue item.
     */
    private static function process_item($item) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        // Mark as processing
        $wpdb->update($table, [
            'status'   => 'processing',
            'attempts' => $item->attempts + 1,
        ], ['id' => $item->id]);

        $result = null;

        switch ($item->action) {
            case 'sync':
                $result = Zicer_Sync::sync_product($item->product_id);
                break;

            case 'delete':
                $meta       = get_post_meta($item->product_id, '_zicer_queue_meta', true);
                $listing_id = isset($meta['listing_id']) ? $meta['listing_id'] : null;
                $result     = Zicer_Sync::delete_listing($item->product_id, $listing_id);
                delete_post_meta($item->product_id, '_zicer_queue_meta');
                break;
        }

        if (is_wp_error($result)) {
            $new_status = ($item->attempts + 1 >= self::MAX_ATTEMPTS) ? 'failed' : 'pending';
            $wpdb->update($table, [
                'status'        => $new_status,
                'error_message' => $result->get_error_message(),
                'processed_at'  => current_time('mysql'),
            ], ['id' => $item->id]);

            // Log failure
            if ($new_status === 'failed') {
                Zicer_Logger::log('error', "Queue {$item->action} failed for product {$item->product_id}", [
                    'error' => $result->get_error_message(),
                ]);
            }
        } else {
            $wpdb->update($table, [
                'status'       => 'completed',
                'processed_at' => current_time('mysql'),
            ], ['id' => $item->id]);
        }
    }

    /**
     * Get queue statistics
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        return [
            'pending'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"),
            'processing' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'processing'"),
            'completed'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'completed'"),
            'failed'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'"),
        ];
    }

    /**
     * Clear failed items
     */
    public static function clear_failed() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';
        $wpdb->delete($table, ['status' => 'failed']);
    }

    /**
     * Remove a single queue item by ID
     *
     * @param int $id Queue item ID.
     * @return bool
     */
    public static function remove($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';
        return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    /**
     * Retry failed items
     */
    public static function retry_failed() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';
        $wpdb->update($table, [
            'status'        => 'pending',
            'attempts'      => 0,
            'error_message' => null,
        ], ['status' => 'failed']);
    }

    /**
     * Get failed items with details
     *
     * @param int $limit Number of items to return.
     * @return array
     */
    public static function get_failed_items($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, p.post_title as product_name
             FROM $table q
             LEFT JOIN {$wpdb->posts} p ON q.product_id = p.ID
             WHERE q.status = 'failed'
             ORDER BY q.processed_at DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get pending items with details
     *
     * @param int $limit Number of items to return.
     * @return array
     */
    public static function get_pending_items($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, p.post_title as product_name
             FROM $table q
             LEFT JOIN {$wpdb->posts} p ON q.product_id = p.ID
             WHERE q.status IN ('pending', 'processing')
             ORDER BY q.created_at ASC
             LIMIT %d",
            $limit
        ));
    }
}
