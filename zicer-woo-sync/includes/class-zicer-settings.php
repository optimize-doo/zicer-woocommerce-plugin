<?php
/**
 * ZICER Settings
 *
 * Handles admin settings pages and options.
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Zicer_Settings
 */
class Zicer_Settings {

    /**
     * Initialize settings
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_ajax_zicer_test_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_zicer_confirm_new_account', [__CLASS__, 'ajax_confirm_new_account']);
        add_action('wp_ajax_zicer_disconnect', [__CLASS__, 'ajax_disconnect']);
        add_action('wp_ajax_zicer_clear_all_listings', [__CLASS__, 'ajax_clear_all_listings']);
        add_action('wp_ajax_zicer_refresh_rate_limit', [__CLASS__, 'ajax_refresh_rate_limit']);
        add_action('wp_ajax_zicer_fetch_categories', [__CLASS__, 'ajax_fetch_categories']);
        add_action('wp_ajax_zicer_fetch_regions', [__CLASS__, 'ajax_fetch_regions']);
        add_action('wp_ajax_zicer_fetch_cities', [__CLASS__, 'ajax_fetch_cities']);
        add_action('wp_ajax_zicer_suggest_category', [__CLASS__, 'ajax_suggest_category']);
        add_action('wp_ajax_zicer_retry_failed', [__CLASS__, 'ajax_retry_failed']);
        add_action('wp_ajax_zicer_clear_failed', [__CLASS__, 'ajax_clear_failed']);
        add_action('wp_ajax_zicer_process_queue', [__CLASS__, 'ajax_process_queue']);
        add_action('wp_ajax_zicer_enqueue_product', [__CLASS__, 'ajax_enqueue_product']);
        add_action('wp_ajax_zicer_dequeue_product', [__CLASS__, 'ajax_dequeue_product']);
        add_action('wp_ajax_zicer_save_product_category', [__CLASS__, 'ajax_save_product_category']);
        add_action('wp_ajax_zicer_get_promotion_price', [__CLASS__, 'ajax_get_promotion_price']);
        add_action('wp_ajax_zicer_promote_listing', [__CLASS__, 'ajax_promote_listing']);
        add_action('wp_ajax_zicer_get_listing_promo_status', [__CLASS__, 'ajax_get_listing_promo_status']);
        add_action('wp_ajax_zicer_get_credits', [__CLASS__, 'ajax_get_credits']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_post_zicer_accept_terms', [__CLASS__, 'handle_accept_terms']);
    }

    /**
     * Add admin menu
     */
    public static function add_menu() {
        add_menu_page(
            __('ZICER Sync', 'zicer-woo-sync'),
            __('ZICER Sync', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-sync',
            [__CLASS__, 'render_settings_page'],
            'dashicons-update',
            56
        );

        add_submenu_page(
            'zicer-sync',
            __('Settings', 'zicer-woo-sync'),
            __('Settings', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-sync',
            [__CLASS__, 'render_settings_page']
        );

        add_submenu_page(
            'zicer-sync',
            __('Category Mapping', 'zicer-woo-sync'),
            __('Categories', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-categories',
            [__CLASS__, 'render_categories_page']
        );

        add_submenu_page(
            'zicer-sync',
            __('Sync Queue', 'zicer-woo-sync'),
            __('Queue', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-status',
            [__CLASS__, 'render_status_page']
        );

        add_submenu_page(
            'zicer-sync',
            __('Log', 'zicer-woo-sync'),
            __('Log', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-log',
            [__CLASS__, 'render_log_page']
        );

        add_submenu_page(
            'zicer-sync',
            __('Help', 'zicer-woo-sync'),
            __('Help', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-help',
            [__CLASS__, 'render_help_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // Connection settings
        register_setting('zicer_settings', 'zicer_api_token');

        // Location settings (from shop profile)
        register_setting('zicer_settings', 'zicer_default_region');
        register_setting('zicer_settings', 'zicer_default_city');

        // Sync settings
        register_setting('zicer_settings', 'zicer_realtime_sync', [
            'type'              => 'string',
            'default'           => '1',
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
        ]);
        register_setting('zicer_settings', 'zicer_delete_on_unavailable', [
            'type'              => 'string',
            'default'           => '1',
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
        ]);
        register_setting('zicer_settings', 'zicer_sync_images', [
            'default' => 'all', // 'all', 'featured', 'none'
        ]);
        register_setting('zicer_settings', 'zicer_max_images', [
            'default' => 10,
        ]);

        // Title settings
        register_setting('zicer_settings', 'zicer_truncate_title', [
            'type'              => 'string',
            'default'           => '0',
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
        ]);
        register_setting('zicer_settings', 'zicer_title_max_length', [
            'default' => 65,
        ]);

        // Description settings
        register_setting('zicer_settings', 'zicer_description_mode', [
            'default' => 'product', // 'prepend', 'append', 'replace', 'product'
        ]);
        register_setting('zicer_settings', 'zicer_description_template', [
            'default' => '',
        ]);

        // Stock settings
        register_setting('zicer_settings', 'zicer_stock_threshold', [
            'default' => 0,
        ]);

        // Price settings
        register_setting('zicer_settings', 'zicer_price_conversion', [
            'default' => 1,
        ]);

        // Default condition
        register_setting('zicer_settings', 'zicer_default_condition', [
            'default' => 'Novo',
        ]);

        // Fallback category
        register_setting('zicer_settings', 'zicer_fallback_category');

        // Debug logging
        register_setting('zicer_settings', 'zicer_debug_logging', [
            'type'              => 'string',
            'default'           => '0',
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
        ]);
    }

    /**
     * Sanitize checkbox value
     *
     * @param mixed $value The value to sanitize.
     * @return string '1' if checked, '0' if not.
     */
    public static function sanitize_checkbox($value) {
        return $value ? '1' : '0';
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_scripts($hook) {
        $screen = get_current_screen();
        $is_product_page = $screen && ($screen->post_type === 'product' || $screen->id === 'edit-product');

        if (strpos($hook, 'zicer') === false && !$is_product_page) {
            return;
        }

        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );

        wp_enqueue_style(
            'zicer-admin',
            ZICER_WOO_PLUGIN_URL . 'admin/css/admin.css',
            ['select2'],
            ZICER_WOO_VERSION
        );

        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );

        // jQuery UI Dialog for custom modals
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

        wp_enqueue_script(
            'zicer-admin',
            ZICER_WOO_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery', 'select2', 'jquery-ui-dialog'],
            ZICER_WOO_VERSION,
            true
        );

        wp_localize_script('zicer-admin', 'zicerAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('zicer_admin'),
            'strings' => [
                'testing'              => __('Testing...', 'zicer-woo-sync'),
                'connected'            => __('Connected!', 'zicer-woo-sync'),
                'error'                => __('Error:', 'zicer-woo-sync'),
                'syncing'              => __('Syncing...', 'zicer-woo-sync'),
                'confirm_bulk'         => __('Are you sure you want to start bulk synchronization?', 'zicer-woo-sync'),
                'confirm_disconnect'   => __('Are you sure you want to disconnect? Previously synced products will keep their ZICER listing IDs.', 'zicer-woo-sync'),
                'confirm_delete'       => __('Are you sure?', 'zicer-woo-sync'),
                'confirm_clear_pending'=> __('Clear all pending items from the queue?', 'zicer-woo-sync'),
                'confirm_clear_failed' => __('Are you sure you want to clear all failed items?', 'zicer-woo-sync'),
                'confirm_remove_item'  => __('Remove this item from the queue?', 'zicer-woo-sync'),
                'connection_failed'    => __('Connection failed', 'zicer-woo-sync'),
                'sync_failed'          => __('Sync failed', 'zicer-woo-sync'),
                'added_to_queue'       => __('Added %d products to queue.', 'zicer-woo-sync'),
                'clearing'             => __('Clearing...', 'zicer-woo-sync'),
                'no_suggestions'       => __('No suggestions', 'zicer-woo-sync'),
                'no_match_found'       => __('No match found', 'zicer-woo-sync'),
                // Modal button labels
                'ok'                   => __('OK', 'zicer-woo-sync'),
                'cancel'               => __('Cancel', 'zicer-woo-sync'),
                'yes'                  => __('Yes', 'zicer-woo-sync'),
                'no'                   => __('No', 'zicer-woo-sync'),
                // Additional UI strings
                'test_connection'      => __('Test Connection', 'zicer-woo-sync'),
                'sync_now'             => __('Sync Now', 'zicer-woo-sync'),
                'clear_recreate'       => __('Clear & Re-create', 'zicer-woo-sync'),
                'sync_all_products'    => __('Sync All Products', 'zicer-woo-sync'),
                'complete'             => __('Complete!', 'zicer-woo-sync'),
                'items_remaining'      => __('items remaining', 'zicer-woo-sync'),
                'loading'              => __('Loading...', 'zicer-woo-sync'),
                'load_categories'      => __('Load Categories', 'zicer-woo-sync'),
                'refresh'              => __('Refresh', 'zicer-woo-sync'),
                // Different account warning
                'different_account_title'   => __('Different Account', 'zicer-woo-sync'),
                'different_account_warning' => __('You are connecting with a different ZICER account.', 'zicer-woo-sync'),
                'previous'             => __('Previous:', 'zicer-woo-sync'),
                'new'                  => __('New:', 'zicer-woo-sync'),
                'unknown'              => __('Unknown', 'zicer-woo-sync'),
                'different_account_msg'=> __('Your products have sync data linked to the previous account. What would you like to do?', 'zicer-woo-sync'),
                'clear_data'           => __('Clear sync data', 'zicer-woo-sync'),
                'clear_data_desc'      => __('Remove all ZICER listing IDs from your products. Next sync will create new listings on the new account. Your WooCommerce products are not affected.', 'zicer-woo-sync'),
                'keep_data'            => __('Keep sync data', 'zicer-woo-sync'),
                'keep_data_desc'       => __('Keep existing listing IDs. Warning: Syncing will fail because these IDs belong to the old account.', 'zicer-woo-sync'),
                // Queue buttons
                'add_to_queue'         => __('Add to Queue', 'zicer-woo-sync'),
                'remove_from_queue'    => __('Remove from Queue', 'zicer-woo-sync'),
                'added_to_queue_single'=> __('Added to queue', 'zicer-woo-sync'),
                'removed_from_queue'   => __('Removed from queue', 'zicer-woo-sync'),
                // Promotion strings
                'promote'              => __('Promote', 'zicer-woo-sync'),
                'promoting'            => __('Promoting...', 'zicer-woo-sync'),
                'promoted'             => __('Promoted!', 'zicer-woo-sync'),
                'promotion_success'    => __('Listing promoted successfully!', 'zicer-woo-sync'),
                'promotion_failed'     => __('Promotion failed', 'zicer-woo-sync'),
                'premium'              => __('Premium', 'zicer-woo-sync'),
                'super_premium'        => __('Super Premium', 'zicer-woo-sync'),
                'credits'              => __('credits', 'zicer-woo-sync'),
                'your_balance'         => __('Your balance:', 'zicer-woo-sync'),
                'cost'                 => __('Cost:', 'zicer-woo-sync'),
                'days'                 => __('days', 'zicer-woo-sync'),
                'insufficient_credits' => __('Insufficient credits', 'zicer-woo-sync'),
                'top_up_credits'       => __('Top up credits', 'zicer-woo-sync'),
                'select_duration'      => __('Select duration', 'zicer-woo-sync'),
                'select_type'          => __('Select type', 'zicer-woo-sync'),
                'promotion_type'       => __('Promotion type', 'zicer-woo-sync'),
                'promotion_duration'   => __('Duration', 'zicer-woo-sync'),
                'confirm_promotion'    => __('Promote this listing for %d days (%s) for %d credits?', 'zicer-woo-sync'),
                'not_synced'           => __('Product not synced', 'zicer-woo-sync'),
                'sync_first'           => __('Sync this product to ZICER first before promoting.', 'zicer-woo-sync'),
                'select_variation'     => __('Select a variation', 'zicer-woo-sync'),
                'loading_status'       => __('Loading...', 'zicer-woo-sync'),
                'expires'              => __('Expires:', 'zicer-woo-sync'),
                'days_remaining'       => __('%d days remaining', 'zicer-woo-sync'),
                'hours_remaining'      => __('%d hours remaining', 'zicer-woo-sync'),
            ],
        ]);
    }

    /**
     * AJAX: Test connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $token = sanitize_text_field(wp_unslash($_POST['token']));
        $api   = Zicer_API_Client::instance();
        $api->set_token($token);

        $result = $api->validate_connection();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Save token and get shop info
        update_option('zicer_api_token', $token);

        $shop = $api->get_shop();
        $shop_data = null;
        $has_shop = !is_wp_error($shop) && !empty($shop['title']);

        if ($has_shop) {
            $shop_data = $shop;
            // Auto-populate region/city from shop only if not already set
            if (!get_option('zicer_default_region') && isset($shop['region']['id'])) {
                update_option('zicer_default_region', $shop['region']['id']);
            }
            if (!get_option('zicer_default_city') && isset($shop['city']['id'])) {
                update_option('zicer_default_city', $shop['city']['id']);
            }
        }

        $rate_limit = $api->get_rate_limit_status();
        $new_user_id = $result['uuid'] ?? $result['id'] ?? '';
        $new_user_email = $result['email'] ?? '';

        // Check if connecting with a different account (get previous values BEFORE updating)
        $previous_user_id = get_option('zicer_connected_user_id', '');
        $previous_user_email = get_option('zicer_connected_user_email', '');
        $is_different_account = $previous_user_id && $new_user_id && $previous_user_id !== $new_user_id;

        // Only store the connected user ID if NOT a different account
        // If different account, user must choose clear/keep first (handled via separate AJAX)
        if ($new_user_id && !$is_different_account) {
            update_option('zicer_connected_user_id', $new_user_id);
            update_option('zicer_connected_user_email', $new_user_email);
        }

        // Don't save connection status if different account - user must choose first
        if ($is_different_account) {
            // Revert the token save - user hasn't confirmed yet
            delete_option('zicer_api_token');

            wp_send_json_success([
                'user'              => $result,
                'shop'              => $shop_data,
                'has_shop'          => $has_shop,
                'rate_limit'        => $rate_limit['limit'],
                'different_account' => true,
                'previous_email'    => $previous_user_email,
                'new_token'         => $token, // Send token back so JS can save it after user confirms
            ]);
            return;
        }

        update_option('zicer_connection_status', [
            'connected'       => true,
            'user'            => $new_user_email,
            'user_id'         => $new_user_id,
            'shop'            => $shop_data['title'] ?? null,
            'has_shop'        => $has_shop,
            'last_check'      => current_time('mysql'),
            'rate_limit'      => $rate_limit['limit'],
            'rate_remaining'  => $rate_limit['remaining'],
        ]);

        wp_send_json_success([
            'user'              => $result,
            'shop'              => $shop_data,
            'has_shop'          => $has_shop,
            'rate_limit'        => $rate_limit['limit'],
            'different_account' => $is_different_account,
            'previous_email'    => $is_different_account ? $previous_user_email : null,
        ]);
    }

    /**
     * AJAX: Confirm connecting with new account (after different account warning)
     */
    public static function ajax_confirm_new_account() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $clear_data = isset($_POST['clear_data']) && $_POST['clear_data'] === 'true';

        if (empty($token)) {
            wp_send_json_error(__('Token is required.', 'zicer-woo-sync'));
        }

        // Validate the token again
        $api = Zicer_API_Client::instance();
        $api->set_token($token);
        $result = $api->validate_connection();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Clear listing data if requested
        if ($clear_data) {
            global $wpdb;
            $meta_keys = ['_zicer_listing_id', '_zicer_last_sync', '_zicer_sync_error', '_zicer_synced_images'];
            foreach ($meta_keys as $key) {
                $wpdb->delete($wpdb->postmeta, ['meta_key' => $key], ['%s']);
            }
            Zicer_Logger::log('info', 'Cleared all ZICER listing data for new account');
        }

        // Now save everything
        $new_user_id = $result['uuid'] ?? $result['id'] ?? '';
        $new_user_email = $result['email'] ?? '';

        update_option('zicer_api_token', $token);
        update_option('zicer_connected_user_id', $new_user_id);
        update_option('zicer_connected_user_email', $new_user_email);

        $shop = $api->get_shop();
        $shop_data = null;
        $has_shop = !is_wp_error($shop) && !empty($shop['title']);

        if ($has_shop) {
            $shop_data = $shop;
            if (!get_option('zicer_default_region') && isset($shop['region']['id'])) {
                update_option('zicer_default_region', $shop['region']['id']);
            }
            if (!get_option('zicer_default_city') && isset($shop['city']['id'])) {
                update_option('zicer_default_city', $shop['city']['id']);
            }
        }

        $rate_limit = $api->get_rate_limit_status();

        update_option('zicer_connection_status', [
            'connected'       => true,
            'user'            => $new_user_email,
            'user_id'         => $new_user_id,
            'shop'            => $shop_data['title'] ?? null,
            'has_shop'        => $has_shop,
            'last_check'      => current_time('mysql'),
            'rate_limit'      => $rate_limit['limit'],
            'rate_remaining'  => $rate_limit['remaining'],
        ]);

        wp_send_json_success(['cleared' => $clear_data]);
    }

    /**
     * Handle terms acceptance form
     */
    public static function handle_accept_terms() {
        if (!isset($_POST['zicer_terms_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zicer_terms_nonce'])), 'zicer_accept_terms')) {
            wp_die(__('Security check failed.', 'zicer-woo-sync'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission.', 'zicer-woo-sync'));
        }

        if (!empty($_POST['zicer_accept_terms'])) {
            update_option('zicer_terms_accepted', true);
        }

        wp_safe_redirect(admin_url('admin.php?page=zicer-sync'));
        exit;
    }

    /**
     * AJAX: Disconnect / clear token
     */
    public static function ajax_disconnect() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        delete_option('zicer_api_token');
        delete_option('zicer_connection_status');
        delete_option('zicer_terms_accepted');
        // Note: zicer_connected_user_id and zicer_connected_user_email are kept
        // to detect if a different account connects later

        wp_send_json_success();
    }

    /**
     * AJAX: Clear all ZICER listing data from products
     */
    public static function ajax_clear_all_listings() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        global $wpdb;

        // Delete all ZICER-related post meta
        $meta_keys = [
            '_zicer_listing_id',
            '_zicer_last_sync',
            '_zicer_sync_error',
            '_zicer_synced_images',
        ];

        $deleted = 0;
        foreach ($meta_keys as $key) {
            $deleted += $wpdb->delete(
                $wpdb->postmeta,
                ['meta_key' => $key],
                ['%s']
            );
        }

        // Clear the stored user ID so next connect is treated as fresh
        delete_option('zicer_connected_user_id');
        delete_option('zicer_connected_user_email');

        Zicer_Logger::log('info', "Cleared all ZICER listing data ($deleted meta entries)");

        wp_send_json_success(['deleted' => $deleted]);
    }

    /**
     * AJAX: Refresh rate limit info
     */
    public static function ajax_refresh_rate_limit() {
        check_ajax_referer('zicer_admin', 'nonce');

        $api = Zicer_API_Client::instance();
        $result = $api->validate_connection();

        // Force update the stored rate limit
        update_option('zicer_rate_limit_info', [
            'limit'     => $api->get_rate_limit_status()['limit'],
            'remaining' => $api->get_rate_limit_status()['remaining'],
            'reset'     => $api->get_rate_limit_status()['reset'],
            'updated'   => time(),
        ], false);

        $info = get_option('zicer_rate_limit_info', []);

        wp_send_json_success([
            'limit'     => $info['limit'] ?? 60,
            'remaining' => $info['remaining'] ?? 60,
            'updated'   => __('just now', 'zicer-woo-sync'),
        ]);
    }

    /**
     * AJAX: Fetch categories
     */
    public static function ajax_fetch_categories() {
        check_ajax_referer('zicer_admin', 'nonce');

        $api        = Zicer_API_Client::instance();
        $categories = [];
        $page       = 1;

        do {
            $result = $api->get_categories($page);
            if (is_wp_error($result)) {
                break;
            }

            $categories = array_merge($categories, $result['data'] ?? []);
            $page++;
        } while (isset($result['meta']['last_page']) && $page <= $result['meta']['last_page']);

        update_option('zicer_categories_cache', $categories);
        update_option('zicer_categories_cache_time', time());

        // Clean up stale mappings (categories that no longer exist)
        self::cleanup_stale_mappings($categories);

        wp_send_json_success($categories);
    }

    /**
     * Remove mappings for categories that no longer exist
     *
     * @param array $categories Current ZICER categories.
     */
    private static function cleanup_stale_mappings($categories) {
        // Build list of valid category IDs
        $valid_ids = [];
        foreach ($categories as $category) {
            $valid_ids[] = $category['uuid'] ?? $category['id'] ?? null;
        }
        $valid_ids = array_filter($valid_ids);

        // Clean up category mappings
        $mapping = get_option('zicer_category_mapping', []);
        $cleaned = false;
        foreach ($mapping as $wc_term_id => $zicer_id) {
            if (!empty($zicer_id) && !in_array($zicer_id, $valid_ids, true)) {
                unset($mapping[$wc_term_id]);
                $cleaned = true;
            }
        }
        if ($cleaned) {
            update_option('zicer_category_mapping', $mapping);
        }

        // Clean up fallback category
        $fallback = get_option('zicer_fallback_category', '');
        if (!empty($fallback) && !in_array($fallback, $valid_ids, true)) {
            delete_option('zicer_fallback_category');
        }
    }

    /**
     * AJAX: Fetch regions
     */
    public static function ajax_fetch_regions() {
        check_ajax_referer('zicer_admin', 'nonce');

        $api    = Zicer_API_Client::instance();
        $result = $api->get_regions();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Flatten regions including cantons for easier selection
        $regions = self::flatten_regions($result['data'] ?? []);

        update_option('zicer_regions_cache', $regions);
        wp_send_json_success($regions);
    }

    /**
     * Flatten regions hierarchy into a flat list
     *
     * @param array  $regions     Regions array.
     * @param string $parent_name Parent region name for display.
     * @param bool   $is_child    Whether these are child regions.
     * @return array Flattened regions.
     */
    private static function flatten_regions($regions, $parent_name = '', $is_child = false) {
        $flat = [];

        foreach ($regions as $region) {
            $has_children = !empty($region['cantons']);

            // Top-level regions with children are just group headers
            if (!$is_child && $has_children) {
                $flat[] = [
                    'uuid'     => '',
                    'title'    => $region['title'],
                    'disabled' => true,
                ];
                $flat = array_merge($flat, self::flatten_regions($region['cantons'], $region['title'], true));
            } else {
                // Selectable region (child or top-level without children)
                $title = $parent_name ? "— {$region['title']}" : $region['title'];
                $flat[] = [
                    'uuid'     => $region['uuid'] ?? $region['id'] ?? '',
                    'title'    => $title,
                    'disabled' => false,
                ];
            }
        }

        return $flat;
    }

    /**
     * AJAX: Fetch cities for a region
     */
    public static function ajax_fetch_cities() {
        check_ajax_referer('zicer_admin', 'nonce');

        $region_id = isset($_POST['region_id']) ? sanitize_text_field(wp_unslash($_POST['region_id'])) : '';

        if (empty($region_id)) {
            wp_send_json_error(__('Region ID is required.', 'zicer-woo-sync'));
        }

        $api    = Zicer_API_Client::instance();
        $result = $api->get_cities($region_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Handle both 'data' and 'hydra:member' response formats
        $cities = $result['data'] ?? $result['hydra:member'] ?? [];
        wp_send_json_success($cities);
    }

    /**
     * AJAX: Suggest category
     */
    public static function ajax_suggest_category() {
        check_ajax_referer('zicer_admin', 'nonce');

        $title = sanitize_text_field(wp_unslash($_POST['title']));
        $api   = Zicer_API_Client::instance();
        $result = $api->get_category_suggestions($title);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result['suggestions'] ?? []);
    }

    /**
     * AJAX: Retry failed items
     */
    public static function ajax_retry_failed() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        Zicer_Queue::retry_failed();
        wp_send_json_success();
    }

    /**
     * AJAX: Clear failed items
     */
    public static function ajax_clear_failed() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        Zicer_Queue::clear_failed();
        wp_send_json_success();
    }

    /**
     * AJAX: Process queue manually
     */
    public static function ajax_process_queue() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        // Clear old stats on first call (when there are no processing items yet)
        $stats = Zicer_Queue::get_stats();
        if ($stats['processing'] === 0 && $stats['completed'] > 0) {
            Zicer_Queue::clear_completed();
            Zicer_Queue::clear_failed();
        }

        Zicer_Queue::process();
        wp_send_json_success(Zicer_Queue::get_stats());
    }

    /**
     * AJAX: Add product to sync queue
     */
    public static function ajax_enqueue_product() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'zicer-woo-sync'));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Product not found.', 'zicer-woo-sync'));
        }

        $queued = 0;

        // For variable products, queue all variations
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                Zicer_Queue::add($variation_id, 'sync');
                $queued++;
            }
        } else {
            Zicer_Queue::add($product_id, 'sync');
            $queued++;
        }

        wp_send_json_success(['queued' => $queued]);
    }

    /**
     * AJAX: Remove product from queue
     */
    public static function ajax_dequeue_product() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'zicer-woo-sync'));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Product not found.', 'zicer-woo-sync'));
        }

        $removed = 0;

        // For variable products, dequeue all variations
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                if (Zicer_Queue::remove_pending($variation_id)) {
                    $removed++;
                }
            }
        } else {
            if (Zicer_Queue::remove_pending($product_id)) {
                $removed++;
            }
        }

        wp_send_json_success(['removed' => $removed]);
    }

    /**
     * AJAX: Save product ZICER category
     */
    public static function ajax_save_product_category() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $category   = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';

        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'zicer-woo-sync'));
        }

        update_post_meta($product_id, '_zicer_category', $category);
        wp_send_json_success();
    }

    /**
     * AJAX: Get promotion price preview
     */
    public static function ajax_get_promotion_price() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $days  = isset($_POST['days']) ? (int) $_POST['days'] : 1;
        $super = isset($_POST['super']) && $_POST['super'] === 'true';

        $api    = Zicer_API_Client::instance();
        $result = $api->get_promotion_price($days, $super);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Promote a listing
     */
    public static function ajax_promote_listing() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $days       = isset($_POST['days']) ? (int) $_POST['days'] : 1;
        $super      = isset($_POST['super']) && $_POST['super'] === 'true';

        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'zicer-woo-sync'));
        }

        $listing_id = get_post_meta($product_id, '_zicer_listing_id', true);
        if (!$listing_id) {
            wp_send_json_error(__('Product is not synced to ZICER.', 'zicer-woo-sync'));
        }

        $api    = Zicer_API_Client::instance();
        $result = $api->promote_listing($listing_id, $days, $super);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        Zicer_Logger::log('info', sprintf(
            'Promoted listing %s for %d days (%s)',
            $listing_id,
            $days,
            $super ? 'Super Premium' : 'Premium'
        ));

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get listing promotion status
     */
    public static function ajax_get_listing_promo_status() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'zicer-woo-sync'));
        }

        $listing_id = get_post_meta($product_id, '_zicer_listing_id', true);
        if (!$listing_id) {
            wp_send_json_error(__('Product is not synced to ZICER.', 'zicer-woo-sync'));
        }

        $api     = Zicer_API_Client::instance();
        $listing = $api->get_listing($listing_id);

        if (is_wp_error($listing)) {
            wp_send_json_error($listing->get_error_message());
        }

        $is_promoted    = !empty($listing['premium']) || !empty($listing['superPremium']);
        $promotion_type = !empty($listing['superPremium']) ? 'super' : 'premium';
        $featured_until = $listing['featuredUntil'] ?? '';

        wp_send_json_success([
            'is_promoted'     => $is_promoted,
            'promotion_type'  => $is_promoted ? $promotion_type : '',
            'featured_until'  => $featured_until,
        ]);
    }

    /**
     * AJAX: Get user credits
     */
    public static function ajax_get_credits() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission.', 'zicer-woo-sync'));
        }

        $api    = Zicer_API_Client::instance();
        $result = $api->get_credits();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'credits' => $result['credits'] ?? 0,
        ]);
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Render categories page
     */
    public static function render_categories_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/category-mapping.php';
    }

    /**
     * Render status page
     */
    public static function render_status_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/sync-status.php';
    }

    /**
     * Render log page
     */
    public static function render_log_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/sync-log.php';
    }

    /**
     * Render help page
     */
    public static function render_help_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/help-page.php';
    }

    /**
     * Get available conditions
     *
     * @return array
     */
    public static function get_conditions() {
        return [
            'Novo'          => __('New', 'zicer-woo-sync'),
            'Otvoreno'      => __('Open box', 'zicer-woo-sync'),
            'Korišteno'     => __('Used', 'zicer-woo-sync'),
            'Popravljeno'   => __('Refurbished', 'zicer-woo-sync'),
            'Nije ispravno' => __('Not working', 'zicer-woo-sync'),
            'Nije navedeno' => __('Not specified', 'zicer-woo-sync'),
        ];
    }
}
