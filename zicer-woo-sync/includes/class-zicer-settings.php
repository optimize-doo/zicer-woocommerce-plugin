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
        add_action('wp_ajax_zicer_fetch_categories', [__CLASS__, 'ajax_fetch_categories']);
        add_action('wp_ajax_zicer_fetch_regions', [__CLASS__, 'ajax_fetch_regions']);
        add_action('wp_ajax_zicer_fetch_cities', [__CLASS__, 'ajax_fetch_cities']);
        add_action('wp_ajax_zicer_suggest_category', [__CLASS__, 'ajax_suggest_category']);
        add_action('wp_ajax_zicer_retry_failed', [__CLASS__, 'ajax_retry_failed']);
        add_action('wp_ajax_zicer_clear_failed', [__CLASS__, 'ajax_clear_failed']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
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
            __('Sync Status', 'zicer-woo-sync'),
            __('Status', 'zicer-woo-sync'),
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
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // Connection settings
        register_setting('zicer_settings', 'zicer_api_token');
        register_setting('zicer_settings', 'zicer_connection_status');

        // Location settings (from shop profile)
        register_setting('zicer_settings', 'zicer_default_region');
        register_setting('zicer_settings', 'zicer_default_city');

        // Sync settings
        register_setting('zicer_settings', 'zicer_realtime_sync', [
            'default' => '1',
        ]);
        register_setting('zicer_settings', 'zicer_delete_on_unavailable', [
            'default' => '1',
        ]);
        register_setting('zicer_settings', 'zicer_sync_images', [
            'default' => 'all', // 'all', 'featured', 'none'
        ]);
        register_setting('zicer_settings', 'zicer_max_images', [
            'default' => 10,
        ]);

        // Title settings
        register_setting('zicer_settings', 'zicer_truncate_title', [
            'default' => '0',
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
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'zicer') === false && get_post_type() !== 'product') {
            return;
        }

        wp_enqueue_style(
            'zicer-admin',
            ZICER_WOO_PLUGIN_URL . 'admin/css/admin.css',
            [],
            ZICER_WOO_VERSION
        );

        wp_enqueue_script(
            'zicer-admin',
            ZICER_WOO_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            ZICER_WOO_VERSION,
            true
        );

        wp_localize_script('zicer-admin', 'zicerAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('zicer_admin'),
            'strings' => [
                'testing'      => __('Testing...', 'zicer-woo-sync'),
                'connected'    => __('Connected!', 'zicer-woo-sync'),
                'error'        => __('Error:', 'zicer-woo-sync'),
                'syncing'      => __('Syncing...', 'zicer-woo-sync'),
                'confirm_bulk' => __('Are you sure you want to start bulk synchronization?', 'zicer-woo-sync'),
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
        if (!is_wp_error($shop) && isset($shop['region'])) {
            update_option('zicer_default_region', $shop['region']['id']);
            if (isset($shop['city'])) {
                update_option('zicer_default_city', $shop['city']['id']);
            }
        }

        update_option('zicer_connection_status', [
            'connected'  => true,
            'user'       => $result['email'] ?? '',
            'shop'       => $shop['title'] ?? null,
            'last_check' => current_time('mysql'),
        ]);

        wp_send_json_success([
            'user' => $result,
            'shop' => $shop,
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

        wp_send_json_success($categories);
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
