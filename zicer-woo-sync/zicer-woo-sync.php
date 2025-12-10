<?php
/**
 * Plugin Name: ZICER WooCommerce Sync
 * Plugin URI: https://zicer.ba
 * Description: Synchronize WooCommerce products with ZICER marketplace
 * Version: 1.0.0
 * Author: ZICER
 * Text Domain: zicer-woo-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZICER_WOO_VERSION', '1.0.0');
define('ZICER_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZICER_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZICER_API_BASE_URL', 'https://api.zicer.ba/api');

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'Zicer_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $file = ZICER_WOO_PLUGIN_DIR . 'includes/class-' .
            strtolower(str_replace('_', '-', $class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Plugin Update Checker - enables automatic updates from GitHub
 */
if (file_exists(ZICER_WOO_PLUGIN_DIR . 'vendor/autoload.php')) {
    require ZICER_WOO_PLUGIN_DIR . 'vendor/autoload.php';

    $zicer_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/optimize-doo/zicer-woocommerce-plugin/',
        __FILE__,
        'zicer-woo-sync'
    );

    // Use GitHub releases (create releases like v1.0.0)
    $zicer_update_checker->getVcsApi()->enableReleaseAssets();
}

/**
 * Initialize plugin after all plugins are loaded
 */
add_action('plugins_loaded', function () {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>' .
                 esc_html__('ZICER Sync requires WooCommerce plugin.', 'zicer-woo-sync') .
                 '</p></div>';
        });
        return;
    }

    // Load text domain for translations
    load_plugin_textdomain(
        'zicer-woo-sync',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Initialize components
    if (class_exists('Zicer_Settings')) {
        Zicer_Settings::init();
    }
    if (class_exists('Zicer_Sync')) {
        Zicer_Sync::init();
    }
    if (class_exists('Zicer_Product_Meta')) {
        Zicer_Product_Meta::init();
    }
    if (class_exists('Zicer_Queue')) {
        Zicer_Queue::init();
    }
});

/**
 * Activation hook - create database tables and schedule cron
 */
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Sync queue table
    $table_queue = $wpdb->prefix . 'zicer_sync_queue';
    $sql_queue = "CREATE TABLE IF NOT EXISTS $table_queue (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        action varchar(20) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        attempts int(11) DEFAULT 0,
        error_message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        processed_at datetime,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY status (status)
    ) $charset_collate;";

    // Sync log table
    $table_log = $wpdb->prefix . 'zicer_sync_log';
    $sql_log = "CREATE TABLE IF NOT EXISTS $table_log (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20),
        listing_id varchar(36),
        action varchar(20) NOT NULL,
        status varchar(20) NOT NULL,
        message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_id (product_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_queue);
    dbDelta($sql_log);

    // Schedule cron for queue processing
    if (!wp_next_scheduled('zicer_process_queue')) {
        wp_schedule_event(time(), 'every_minute', 'zicer_process_queue');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Deactivation hook - clear scheduled cron
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('zicer_process_queue');
});

/**
 * Add custom cron interval (every minute)
 */
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => __('Every Minute', 'zicer-woo-sync'),
    ];
    return $schedules;
});

/**
 * Declare WooCommerce HPOS (High-Performance Order Storage) compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});
