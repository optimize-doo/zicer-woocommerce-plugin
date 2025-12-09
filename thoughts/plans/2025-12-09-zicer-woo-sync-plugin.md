# ZICER WooCommerce Sync Plugin - Implementation Plan

## Overview

WordPress/WooCommerce plugin that synchronizes WooCommerce products with ZICER marketplace listings. Supports real-time sync, bulk operations, and comprehensive mapping configuration.

## Desired End State

- Users can connect WooCommerce store to ZICER via API token
- Products automatically sync to ZICER on create/update/delete
- Full control over category mapping, description templates, and sync behavior
- Bulk sync capability for existing products
- Respects ZICER API rate limits

## What We're NOT Doing

- WooCommerce order integration (ZICER doesn't have order API)
- Two-way sync (ZICER → WooCommerce)
- ZICER shop/profile management (use ZICER dashboard)
- Promotion/premium listing management
- Catalog management

---

## Plugin Structure

```
zicer-woo-sync/
├── zicer-woo-sync.php              # Main plugin file
├── includes/
│   ├── class-zicer-api-client.php  # API client with rate limiting
│   ├── class-zicer-settings.php    # Settings page handler
│   ├── class-zicer-sync.php        # Core sync logic
│   ├── class-zicer-product-meta.php # Product meta box
│   ├── class-zicer-category-map.php # Category mapping
│   ├── class-zicer-queue.php       # Background job queue
│   └── class-zicer-logger.php      # Logging utility
├── admin/
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   └── views/
│       ├── settings-page.php
│       ├── category-mapping.php
│       ├── sync-status.php
│       └── product-meta-box.php
├── languages/
│   ├── zicer-woo-sync.pot
│   ├── zicer-woo-sync-bs_BA.po
│   └── zicer-woo-sync-bs_BA.mo
└── readme.txt
```

---

## Phase 1: Core Infrastructure

### 1.1 Main Plugin File

**File**: `zicer-woo-sync.php`

```php
<?php
/**
 * Plugin Name: ZICER WooCommerce Sync
 * Plugin URI: https://zicer.ba
 * Description: Sinhronizacija WooCommerce proizvoda sa ZICER marketplace-om
 * Version: 1.0.0
 * Author: ZICER
 * Text Domain: zicer-woo-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) exit;

define('ZICER_WOO_VERSION', '1.0.0');
define('ZICER_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZICER_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZICER_API_BASE_URL', 'https://api.zicer.ba/api');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Zicer_';
    if (strpos($class, $prefix) !== 0) return;

    $file = ZICER_WOO_PLUGIN_DIR . 'includes/class-' .
            strtolower(str_replace('_', '-', $class)) . '.php';
    if (file_exists($file)) require $file;
});

// Initialize plugin
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' .
                 __('ZICER Sync zahtijeva WooCommerce plugin.', 'zicer-woo-sync') .
                 '</p></div>';
        });
        return;
    }

    load_plugin_textdomain('zicer-woo-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');

    Zicer_Settings::init();
    Zicer_Sync::init();
    Zicer_Product_Meta::init();
    Zicer_Queue::init();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Sync queue table
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zicer_sync_queue (
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
    $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zicer_sync_log (
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Schedule cron for queue processing
    if (!wp_next_scheduled('zicer_process_queue')) {
        wp_schedule_event(time(), 'every_minute', 'zicer_process_queue');
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('zicer_process_queue');
});

// Custom cron interval
add_filter('cron_schedules', function($schedules) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display' => __('Svake minute', 'zicer-woo-sync')
    ];
    return $schedules;
});
```

### 1.2 API Client with Rate Limiting

**File**: `includes/class-zicer-api-client.php`

```php
<?php
class Zicer_API_Client {
    private static $instance = null;
    private $api_token;
    private $rate_limit_remaining = 60;
    private $rate_limit_reset = 0;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_token = get_option('zicer_api_token', '');
    }

    public function set_token($token) {
        $this->api_token = $token;
    }

    public function request($method, $endpoint, $data = null, $is_multipart = false) {
        // Check rate limit
        if ($this->rate_limit_remaining <= 0 && time() < $this->rate_limit_reset) {
            $wait = $this->rate_limit_reset - time();
            return new WP_Error('rate_limit', sprintf(
                __('Rate limit dostignut. Pokušajte ponovo za %d sekundi.', 'zicer-woo-sync'),
                $wait
            ));
        }

        $url = ZICER_API_BASE_URL . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($data !== null) {
            if ($is_multipart) {
                // Handle file upload separately
                $args['headers']['Content-Type'] = 'multipart/form-data';
                $args['body'] = $data;
            } else {
                $content_type = ($method === 'PATCH')
                    ? 'application/merge-patch+json'
                    : 'application/json';
                $args['headers']['Content-Type'] = $content_type;
                $args['body'] = json_encode($data);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Zicer_Logger::log('error', 'API request failed: ' . $response->get_error_message());
            return $response;
        }

        // Update rate limit info from headers
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['x-ratelimit-remaining'])) {
            $this->rate_limit_remaining = (int) $headers['x-ratelimit-remaining'];
        }
        if (isset($headers['x-ratelimit-reset'])) {
            $this->rate_limit_reset = (int) $headers['x-ratelimit-reset'];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code >= 400) {
            $error_msg = isset($decoded['detail']) ? $decoded['detail'] :
                        (isset($decoded['message']) ? $decoded['message'] : 'Unknown error');
            Zicer_Logger::log('error', "API error ($code): $error_msg", [
                'endpoint' => $endpoint,
                'response' => $decoded
            ]);
            return new WP_Error('api_error', $error_msg, ['status' => $code, 'body' => $decoded]);
        }

        return $decoded;
    }

    // Convenience methods
    public function get($endpoint) {
        return $this->request('GET', $endpoint);
    }

    public function post($endpoint, $data) {
        return $this->request('POST', $endpoint, $data);
    }

    public function patch($endpoint, $data) {
        return $this->request('PATCH', $endpoint, $data);
    }

    public function delete($endpoint) {
        return $this->request('DELETE', $endpoint);
    }

    // API Methods
    public function validate_connection() {
        return $this->get('/me');
    }

    public function get_shop() {
        return $this->get('/shop');
    }

    public function get_categories($page = 1) {
        return $this->get("/categories?page=$page&itemsPerPage=100");
    }

    public function get_category_suggestions($title) {
        return $this->get('/categories/suggest?title=' . urlencode($title));
    }

    public function get_regions() {
        return $this->get('/regions?exists[parent]=false&itemsPerPage=100');
    }

    public function get_cities($region_id) {
        return $this->get("/regions/$region_id/cities?itemsPerPage=500");
    }

    public function create_listing($data) {
        return $this->post('/listings', $data);
    }

    public function update_listing($id, $data) {
        return $this->patch("/listings/$id", $data);
    }

    public function delete_listing($id) {
        return $this->delete("/listings/$id");
    }

    public function get_listing($id) {
        return $this->get("/listings/$id");
    }

    public function get_listings($page = 1, $per_page = 100) {
        return $this->get("/listings?page=$page&itemsPerPage=$per_page");
    }

    public function upload_media($listing_id, $file_path, $position = 0) {
        $boundary = wp_generate_password(24, false);
        $file_name = basename($file_path);
        $file_type = mime_content_type($file_path);
        $file_content = file_get_contents($file_path);

        $body = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$file_name\"\r\n";
        $body .= "Content-Type: $file_type\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"position\"\r\n\r\n";
        $body .= $position . "\r\n";
        $body .= "--$boundary--\r\n";

        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => "multipart/form-data; boundary=$boundary",
            ],
            'body' => $body,
            'timeout' => 120,
        ];

        $response = wp_remote_request(
            ZICER_API_BASE_URL . "/listings/$listing_id/media",
            $args
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return new WP_Error('upload_error', 'Failed to upload media', ['status' => $code]);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function get_rate_limit_status() {
        return [
            'remaining' => $this->rate_limit_remaining,
            'reset' => $this->rate_limit_reset,
            'reset_in' => max(0, $this->rate_limit_reset - time())
        ];
    }
}
```

### Success Criteria Phase 1:

#### Automated:
- [ ] Plugin activates without errors
- [ ] Database tables created successfully
- [ ] Cron job scheduled

#### Manual:
- [ ] API client can connect to ZICER
- [ ] Rate limiting headers are parsed correctly

---

## Phase 2: Settings & Configuration

### 2.1 Settings Class

**File**: `includes/class-zicer-settings.php`

```php
<?php
class Zicer_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_ajax_zicer_test_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_zicer_fetch_categories', [__CLASS__, 'ajax_fetch_categories']);
        add_action('wp_ajax_zicer_fetch_regions', [__CLASS__, 'ajax_fetch_regions']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

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
            __('Postavke', 'zicer-woo-sync'),
            __('Postavke', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-sync',
            [__CLASS__, 'render_settings_page']
        );

        add_submenu_page(
            'zicer-sync',
            __('Mapiranje kategorija', 'zicer-woo-sync'),
            __('Kategorije', 'zicer-woo-sync'),
            'manage_woocommerce',
            'zicer-categories',
            [__CLASS__, 'render_categories_page']
        );

        add_submenu_page(
            'zicer-sync',
            __('Status sinhronizacije', 'zicer-woo-sync'),
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

    public static function register_settings() {
        // Connection settings
        register_setting('zicer_settings', 'zicer_api_token');
        register_setting('zicer_settings', 'zicer_connection_status');

        // Location settings (from shop profile)
        register_setting('zicer_settings', 'zicer_default_region');
        register_setting('zicer_settings', 'zicer_default_city');

        // Sync settings
        register_setting('zicer_settings', 'zicer_realtime_sync', [
            'default' => '1'
        ]);
        register_setting('zicer_settings', 'zicer_delete_on_unavailable', [
            'default' => '1'
        ]);
        register_setting('zicer_settings', 'zicer_sync_images', [
            'default' => 'all' // 'all', 'featured', 'none'
        ]);
        register_setting('zicer_settings', 'zicer_max_images', [
            'default' => 10
        ]);

        // Title settings
        register_setting('zicer_settings', 'zicer_truncate_title', [
            'default' => '0'
        ]);
        register_setting('zicer_settings', 'zicer_title_max_length', [
            'default' => 65
        ]);

        // Description settings
        register_setting('zicer_settings', 'zicer_description_mode', [
            'default' => 'replace' // 'prepend', 'append', 'replace', 'product'
        ]);
        register_setting('zicer_settings', 'zicer_description_template', [
            'default' => ''
        ]);

        // Stock settings
        register_setting('zicer_settings', 'zicer_stock_threshold', [
            'default' => 0
        ]);

        // Price settings
        register_setting('zicer_settings', 'zicer_price_conversion', [
            'default' => 1
        ]);

        // Default condition
        register_setting('zicer_settings', 'zicer_default_condition', [
            'default' => 'Novo'
        ]);

        // Fallback category
        register_setting('zicer_settings', 'zicer_fallback_category');
    }

    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'zicer') === false) return;

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
            'nonce' => wp_create_nonce('zicer_admin'),
            'strings' => [
                'testing' => __('Testiranje...', 'zicer-woo-sync'),
                'connected' => __('Povezano!', 'zicer-woo-sync'),
                'error' => __('Greška:', 'zicer-woo-sync'),
                'syncing' => __('Sinhronizacija...', 'zicer-woo-sync'),
                'confirm_bulk' => __('Da li ste sigurni da želite pokrenuti bulk sinhronizaciju?', 'zicer-woo-sync'),
            ]
        ]);
    }

    public static function ajax_test_connection() {
        check_ajax_referer('zicer_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Nemate dozvolu.', 'zicer-woo-sync'));
        }

        $token = sanitize_text_field($_POST['token']);
        $api = Zicer_API_Client::instance();
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
            'connected' => true,
            'user' => $result['email'] ?? '',
            'shop' => $shop['title'] ?? null,
            'last_check' => current_time('mysql')
        ]);

        wp_send_json_success([
            'user' => $result,
            'shop' => $shop
        ]);
    }

    public static function ajax_fetch_categories() {
        check_ajax_referer('zicer_admin', 'nonce');

        $api = Zicer_API_Client::instance();
        $categories = [];
        $page = 1;

        do {
            $result = $api->get_categories($page);
            if (is_wp_error($result)) break;

            $categories = array_merge($categories, $result['data'] ?? []);
            $page++;
        } while (isset($result['meta']['last_page']) && $page <= $result['meta']['last_page']);

        update_option('zicer_categories_cache', $categories);
        update_option('zicer_categories_cache_time', time());

        wp_send_json_success($categories);
    }

    public static function ajax_fetch_regions() {
        check_ajax_referer('zicer_admin', 'nonce');

        $api = Zicer_API_Client::instance();
        $result = $api->get_regions();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        update_option('zicer_regions_cache', $result['data'] ?? []);
        wp_send_json_success($result['data'] ?? []);
    }

    public static function render_settings_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public static function render_categories_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/category-mapping.php';
    }

    public static function render_status_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/sync-status.php';
    }

    public static function render_log_page() {
        include ZICER_WOO_PLUGIN_DIR . 'admin/views/sync-log.php';
    }

    public static function get_conditions() {
        return [
            'Novo' => __('Novo', 'zicer-woo-sync'),
            'Otvoreno' => __('Otvoreno (Open box)', 'zicer-woo-sync'),
            'Korišteno' => __('Korišteno', 'zicer-woo-sync'),
            'Popravljeno' => __('Popravljeno (Refurbished)', 'zicer-woo-sync'),
            'Nije ispravno' => __('Nije ispravno', 'zicer-woo-sync'),
            'Nije navedeno' => __('Nije navedeno', 'zicer-woo-sync'),
        ];
    }
}
```

### 2.2 Settings Page View

**File**: `admin/views/settings-page.php`

```php
<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap zicer-settings">
    <h1><?php _e('ZICER Sync Postavke', 'zicer-woo-sync'); ?></h1>

    <?php
    $connection = get_option('zicer_connection_status', []);
    $is_connected = !empty($connection['connected']);
    ?>

    <form method="post" action="options.php">
        <?php settings_fields('zicer_settings'); ?>

        <!-- Connection Section -->
        <div class="zicer-card">
            <h2><?php _e('Konekcija', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php _e('API Token', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="password"
                               name="zicer_api_token"
                               id="zicer_api_token"
                               value="<?php echo esc_attr(get_option('zicer_api_token')); ?>"
                               class="regular-text">
                        <button type="button" id="zicer-test-connection" class="button">
                            <?php _e('Testiraj konekciju', 'zicer-woo-sync'); ?>
                        </button>
                        <p class="description">
                            <?php _e('API token možete kreirati na ZICER platformi u postavkama profila.', 'zicer-woo-sync'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Status', 'zicer-woo-sync'); ?></th>
                    <td>
                        <span id="zicer-connection-status" class="<?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                            <?php if ($is_connected): ?>
                                ✓ <?php printf(__('Povezano kao %s', 'zicer-woo-sync'), esc_html($connection['user'])); ?>
                                <?php if (!empty($connection['shop'])): ?>
                                    (<?php echo esc_html($connection['shop']); ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                ✗ <?php _e('Nije povezano', 'zicer-woo-sync'); ?>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Sync Settings -->
        <div class="zicer-card">
            <h2><?php _e('Sinhronizacija', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php _e('Real-time sinhronizacija', 'zicer-woo-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="zicer_realtime_sync"
                                   value="1"
                                   <?php checked(get_option('zicer_realtime_sync', '1'), '1'); ?>>
                            <?php _e('Automatski sinhronizuj proizvode pri kreiranju/izmjeni', 'zicer-woo-sync'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Brisanje nedostupnih', 'zicer-woo-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="zicer_delete_on_unavailable"
                                   value="1"
                                   <?php checked(get_option('zicer_delete_on_unavailable', '1'), '1'); ?>>
                            <?php _e('Automatski ukloni sa ZICER-a kada proizvod nije dostupan', 'zicer-woo-sync'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Prag zalihe', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="number"
                               name="zicer_stock_threshold"
                               value="<?php echo esc_attr(get_option('zicer_stock_threshold', 0)); ?>"
                               min="0"
                               class="small-text">
                        <p class="description">
                            <?php _e('Proizvod se smatra dostupnim ako je količina veća od ovog broja. 0 = bilo koja količina na stanju.', 'zicer-woo-sync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Title Settings -->
        <div class="zicer-card">
            <h2><?php _e('Naslov', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php _e('Skraćivanje naslova', 'zicer-woo-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="zicer_truncate_title"
                                   value="1"
                                   <?php checked(get_option('zicer_truncate_title', '0'), '1'); ?>>
                            <?php _e('Skrati naslov na maksimalnu dužinu', 'zicer-woo-sync'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Maksimalna dužina', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="number"
                               name="zicer_title_max_length"
                               value="<?php echo esc_attr(get_option('zicer_title_max_length', 65)); ?>"
                               min="10"
                               max="200"
                               class="small-text">
                        <span><?php _e('karaktera', 'zicer-woo-sync'); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Description Settings -->
        <div class="zicer-card">
            <h2><?php _e('Opis', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php _e('Mod opisa', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_description_mode">
                            <option value="product" <?php selected(get_option('zicer_description_mode', 'product'), 'product'); ?>>
                                <?php _e('Koristi opis proizvoda', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="replace" <?php selected(get_option('zicer_description_mode'), 'replace'); ?>>
                                <?php _e('Zamijeni sa šablonom', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="prepend" <?php selected(get_option('zicer_description_mode'), 'prepend'); ?>>
                                <?php _e('Dodaj šablon prije opisa', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="append" <?php selected(get_option('zicer_description_mode'), 'append'); ?>>
                                <?php _e('Dodaj šablon poslije opisa', 'zicer-woo-sync'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Šablon opisa', 'zicer-woo-sync'); ?></th>
                    <td>
                        <?php
                        wp_editor(
                            get_option('zicer_description_template', ''),
                            'zicer_description_template',
                            [
                                'textarea_name' => 'zicer_description_template',
                                'textarea_rows' => 6,
                                'media_buttons' => false,
                            ]
                        );
                        ?>
                        <p class="description">
                            <?php _e('Dostupne varijable: {product_name}, {product_price}, {product_sku}, {shop_name}', 'zicer-woo-sync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Image Settings -->
        <div class="zicer-card">
            <h2><?php _e('Slike', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php _e('Sinhronizacija slika', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_sync_images">
                            <option value="all" <?php selected(get_option('zicer_sync_images', 'all'), 'all'); ?>>
                                <?php _e('Sve slike', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="featured" <?php selected(get_option('zicer_sync_images'), 'featured'); ?>>
                                <?php _e('Samo istaknuta slika', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="none" <?php selected(get_option('zicer_sync_images'), 'none'); ?>>
                                <?php _e('Bez slika', 'zicer-woo-sync'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Maksimalan broj slika', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="number"
                               name="zicer_max_images"
                               value="<?php echo esc_attr(get_option('zicer_max_images', 10)); ?>"
                               min="1"
                               max="20"
                               class="small-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Price Settings -->
        <div class="zicer-card">
            <h2><?php _e('Cijena', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php _e('Konverzija valute', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="number"
                               name="zicer_price_conversion"
                               value="<?php echo esc_attr(get_option('zicer_price_conversion', 1)); ?>"
                               step="0.0001"
                               min="0.0001"
                               class="small-text">
                        <p class="description">
                            <?php printf(
                                __('Multiplikator za konverziju u KM. Trenutna WooCommerce valuta: %s', 'zicer-woo-sync'),
                                get_woocommerce_currency()
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Default Values -->
        <div class="zicer-card">
            <h2><?php _e('Zadane vrijednosti', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php _e('Zadano stanje', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_default_condition">
                            <?php foreach (Zicer_Settings::get_conditions() as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                        <?php selected(get_option('zicer_default_condition', 'Novo'), $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Zadana regija', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_default_region" id="zicer_default_region">
                            <option value=""><?php _e('-- Odaberi --', 'zicer-woo-sync'); ?></option>
                            <!-- Populated via JS -->
                        </select>
                        <button type="button" id="zicer-refresh-regions" class="button">
                            <?php _e('Osvježi', 'zicer-woo-sync'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Zadani grad', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_default_city" id="zicer_default_city">
                            <option value=""><?php _e('-- Odaberi regiju prvo --', 'zicer-woo-sync'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Spremi postavke', 'zicer-woo-sync')); ?>
    </form>
</div>
```

### Success Criteria Phase 2:

#### Automated:
- [ ] Settings page loads without errors
- [ ] Options are saved to database correctly

#### Manual:
- [ ] API connection test works
- [ ] Region/city selection populates correctly
- [ ] All settings persist after save

---

## Phase 3: Category Mapping

### 3.1 Category Mapping Class

**File**: `includes/class-zicer-category-map.php`

```php
<?php
class Zicer_Category_Map {
    public static function get_mapping() {
        return get_option('zicer_category_mapping', []);
    }

    public static function save_mapping($mapping) {
        update_option('zicer_category_mapping', $mapping);
    }

    public static function get_zicer_category($wc_category_id) {
        $mapping = self::get_mapping();

        // Direct mapping
        if (isset($mapping[$wc_category_id])) {
            return $mapping[$wc_category_id];
        }

        // Try parent category
        $term = get_term($wc_category_id, 'product_cat');
        if ($term && $term->parent) {
            return self::get_zicer_category($term->parent);
        }

        // Fallback
        return get_option('zicer_fallback_category', null);
    }

    public static function suggest_category($product_title) {
        $api = Zicer_API_Client::instance();
        $result = $api->get_category_suggestions($product_title);

        if (is_wp_error($result)) {
            return [];
        }

        return $result['suggestions'] ?? [];
    }

    public static function get_cached_categories() {
        $cache_time = get_option('zicer_categories_cache_time', 0);

        // Cache for 24 hours
        if (time() - $cache_time > 86400) {
            return null;
        }

        return get_option('zicer_categories_cache', []);
    }

    public static function flatten_categories($categories, $parent_path = '') {
        $flat = [];

        foreach ($categories as $cat) {
            $path = $parent_path ? "$parent_path > {$cat['title']}" : $cat['title'];
            $flat[] = [
                'id' => $cat['id'],
                'title' => $cat['title'],
                'path' => $path,
                'has_children' => !empty($cat['categories'])
            ];

            if (!empty($cat['categories'])) {
                $flat = array_merge($flat, self::flatten_categories($cat['categories'], $path));
            }
        }

        return $flat;
    }
}
```

### 3.2 Category Mapping View

**File**: `admin/views/category-mapping.php`

```php
<?php if (!defined('ABSPATH')) exit;

$wc_categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
    'orderby' => 'name'
]);

$zicer_categories = Zicer_Category_Map::get_cached_categories();
$flat_categories = $zicer_categories ? Zicer_Category_Map::flatten_categories($zicer_categories) : [];
$mapping = Zicer_Category_Map::get_mapping();
?>

<div class="wrap zicer-categories">
    <h1><?php _e('Mapiranje kategorija', 'zicer-woo-sync'); ?></h1>

    <p class="description">
        <?php _e('Povežite WooCommerce kategorije sa ZICER kategorijama. Proizvodi bez mapiranja koristit će rezervnu kategoriju.', 'zicer-woo-sync'); ?>
    </p>

    <?php if (empty($zicer_categories)): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('ZICER kategorije nisu učitane.', 'zicer-woo-sync'); ?>
                <button type="button" id="zicer-load-categories" class="button">
                    <?php _e('Učitaj kategorije', 'zicer-woo-sync'); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="zicer_save_category_mapping">
        <?php wp_nonce_field('zicer_category_mapping', 'zicer_nonce'); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('WooCommerce kategorija', 'zicer-woo-sync'); ?></th>
                    <th><?php _e('ZICER kategorija', 'zicer-woo-sync'); ?></th>
                    <th><?php _e('Auto-prijedlog', 'zicer-woo-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wc_categories as $wc_cat): ?>
                    <tr>
                        <td>
                            <?php
                            $depth = 0;
                            $parent = $wc_cat->parent;
                            while ($parent) {
                                $depth++;
                                $parent_term = get_term($parent, 'product_cat');
                                $parent = $parent_term ? $parent_term->parent : 0;
                            }
                            echo str_repeat('— ', $depth) . esc_html($wc_cat->name);
                            ?>
                            <span class="count">(<?php echo $wc_cat->count; ?>)</span>
                        </td>
                        <td>
                            <select name="mapping[<?php echo $wc_cat->term_id; ?>]"
                                    class="zicer-category-select"
                                    data-wc-cat="<?php echo esc_attr($wc_cat->name); ?>">
                                <option value=""><?php _e('-- Ne mapiraj --', 'zicer-woo-sync'); ?></option>
                                <?php foreach ($flat_categories as $zcat): ?>
                                    <?php if (!$zcat['has_children']): // Only leaf categories ?>
                                        <option value="<?php echo esc_attr($zcat['id']); ?>"
                                                <?php selected($mapping[$wc_cat->term_id] ?? '', $zcat['id']); ?>>
                                            <?php echo esc_html($zcat['path']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="button"
                                    class="button zicer-suggest-category"
                                    data-term-id="<?php echo $wc_cat->term_id; ?>"
                                    data-term-name="<?php echo esc_attr($wc_cat->name); ?>">
                                <?php _e('Predloži', 'zicer-woo-sync'); ?>
                            </button>
                            <span class="spinner"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button(__('Spremi mapiranje', 'zicer-woo-sync')); ?>
    </form>
</div>
```

### Success Criteria Phase 3:

#### Automated:
- [ ] Category mapping saves correctly

#### Manual:
- [ ] ZICER categories load and display
- [ ] Category suggestions work via API
- [ ] Mapping persists after save

---

## Phase 4: Product Meta & Sync Core

### 4.1 Product Meta Box

**File**: `includes/class-zicer-product-meta.php`

```php
<?php
class Zicer_Product_Meta {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_meta']);
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_general_fields']);
    }

    public static function add_meta_box() {
        add_meta_box(
            'zicer_sync_meta',
            __('ZICER Sync', 'zicer-woo-sync'),
            [__CLASS__, 'render_meta_box'],
            'product',
            'side',
            'default'
        );
    }

    public static function add_general_fields() {
        global $post;

        woocommerce_wp_select([
            'id' => '_zicer_condition',
            'label' => __('ZICER stanje artikla', 'zicer-woo-sync'),
            'options' => array_merge(
                ['' => __('-- Koristi zadano --', 'zicer-woo-sync')],
                Zicer_Settings::get_conditions()
            ),
            'desc_tip' => true,
            'description' => __('Stanje artikla za ZICER listing', 'zicer-woo-sync')
        ]);

        woocommerce_wp_checkbox([
            'id' => '_zicer_exclude',
            'label' => __('Isključi iz ZICER sync', 'zicer-woo-sync'),
            'description' => __('Ne sinhronizuj ovaj proizvod sa ZICER-om', 'zicer-woo-sync')
        ]);
    }

    public static function render_meta_box($post) {
        $listing_id = get_post_meta($post->ID, '_zicer_listing_id', true);
        $last_sync = get_post_meta($post->ID, '_zicer_last_sync', true);
        $sync_error = get_post_meta($post->ID, '_zicer_sync_error', true);
        $excluded = get_post_meta($post->ID, '_zicer_exclude', true);

        wp_nonce_field('zicer_product_meta', 'zicer_meta_nonce');
        ?>
        <div class="zicer-product-meta">
            <?php if ($excluded === 'yes'): ?>
                <p class="zicer-status excluded">
                    <?php _e('Isključeno iz sinhronizacije', 'zicer-woo-sync'); ?>
                </p>
            <?php elseif ($listing_id): ?>
                <p class="zicer-status synced">
                    ✓ <?php _e('Sinhronizovano', 'zicer-woo-sync'); ?>
                </p>
                <p class="zicer-info">
                    <strong>ID:</strong> <?php echo esc_html(substr($listing_id, 0, 8)); ?>...
                    <br>
                    <strong><?php _e('Zadnja sync:', 'zicer-woo-sync'); ?></strong>
                    <?php echo esc_html($last_sync); ?>
                </p>
                <p>
                    <a href="https://zicer.ba/oglas/<?php echo esc_attr($listing_id); ?>"
                       target="_blank" class="button">
                        <?php _e('Pogledaj na ZICER', 'zicer-woo-sync'); ?>
                    </a>
                </p>
            <?php elseif ($sync_error): ?>
                <p class="zicer-status error">
                    ✗ <?php _e('Greška', 'zicer-woo-sync'); ?>
                </p>
                <p class="zicer-error"><?php echo esc_html($sync_error); ?></p>
            <?php else: ?>
                <p class="zicer-status pending">
                    <?php _e('Nije sinhronizovano', 'zicer-woo-sync'); ?>
                </p>
            <?php endif; ?>

            <hr>

            <p>
                <button type="button"
                        class="button zicer-sync-now"
                        data-product-id="<?php echo $post->ID; ?>">
                    <?php _e('Sinhronizuj sada', 'zicer-woo-sync'); ?>
                </button>
            </p>

            <?php if ($listing_id): ?>
                <p>
                    <button type="button"
                            class="button zicer-delete-listing"
                            data-product-id="<?php echo $post->ID; ?>">
                        <?php _e('Ukloni sa ZICER', 'zicer-woo-sync'); ?>
                    </button>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function save_meta($post_id) {
        if (!isset($_POST['zicer_meta_nonce']) ||
            !wp_verify_nonce($_POST['zicer_meta_nonce'], 'zicer_product_meta')) {
            return;
        }

        $condition = isset($_POST['_zicer_condition']) ?
                     sanitize_text_field($_POST['_zicer_condition']) : '';
        $exclude = isset($_POST['_zicer_exclude']) ? 'yes' : 'no';

        update_post_meta($post_id, '_zicer_condition', $condition);
        update_post_meta($post_id, '_zicer_exclude', $exclude);
    }
}
```

### 4.2 Core Sync Class

**File**: `includes/class-zicer-sync.php`

```php
<?php
class Zicer_Sync {
    public static function init() {
        // Real-time hooks
        add_action('woocommerce_update_product', [__CLASS__, 'on_product_save'], 10, 2);
        add_action('woocommerce_new_product', [__CLASS__, 'on_product_save'], 10, 2);
        add_action('before_delete_post', [__CLASS__, 'on_product_delete']);
        add_action('woocommerce_product_set_stock', [__CLASS__, 'on_stock_change']);

        // AJAX handlers
        add_action('wp_ajax_zicer_sync_product', [__CLASS__, 'ajax_sync_product']);
        add_action('wp_ajax_zicer_delete_listing', [__CLASS__, 'ajax_delete_listing']);
        add_action('wp_ajax_zicer_bulk_sync', [__CLASS__, 'ajax_bulk_sync']);

        // Category mapping save
        add_action('admin_post_zicer_save_category_mapping', [__CLASS__, 'save_category_mapping']);
    }

    public static function on_product_save($product_id, $product = null) {
        if (!get_option('zicer_realtime_sync', '1')) return;
        if (get_post_meta($product_id, '_zicer_exclude', true) === 'yes') return;

        if (!$product) {
            $product = wc_get_product($product_id);
        }
        if (!$product) return;

        // Handle variable products
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                Zicer_Queue::add($variation_id, 'sync');
            }
        } else {
            Zicer_Queue::add($product_id, 'sync');
        }
    }

    public static function on_product_delete($post_id) {
        if (get_post_type($post_id) !== 'product') return;

        $listing_id = get_post_meta($post_id, '_zicer_listing_id', true);
        if ($listing_id) {
            Zicer_Queue::add($post_id, 'delete', ['listing_id' => $listing_id]);
        }
    }

    public static function on_stock_change($product) {
        if (!get_option('zicer_realtime_sync', '1')) return;
        if (get_post_meta($product->get_id(), '_zicer_exclude', true) === 'yes') return;

        $listing_id = get_post_meta($product->get_id(), '_zicer_listing_id', true);
        if ($listing_id) {
            Zicer_Queue::add($product->get_id(), 'sync');
        }
    }

    public static function sync_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('invalid_product', 'Product not found');
        }

        // Skip excluded products
        if (get_post_meta($product_id, '_zicer_exclude', true) === 'yes') {
            return new WP_Error('excluded', 'Product excluded from sync');
        }

        // Check availability
        $is_available = self::is_product_available($product);

        // Get existing listing ID
        $listing_id = get_post_meta($product_id, '_zicer_listing_id', true);

        // If not available and delete option enabled, remove listing
        if (!$is_available && $listing_id && get_option('zicer_delete_on_unavailable', '1')) {
            return self::delete_listing($product_id);
        }

        // If not available and no listing, skip
        if (!$is_available && !$listing_id) {
            return new WP_Error('not_available', 'Product not available');
        }

        // Build listing data
        $data = self::build_listing_data($product);
        if (is_wp_error($data)) {
            update_post_meta($product_id, '_zicer_sync_error', $data->get_error_message());
            return $data;
        }

        $api = Zicer_API_Client::instance();

        if ($listing_id) {
            // Update existing
            $result = $api->update_listing($listing_id, $data);
        } else {
            // Create new
            $result = $api->create_listing($data);
        }

        if (is_wp_error($result)) {
            update_post_meta($product_id, '_zicer_sync_error', $result->get_error_message());
            Zicer_Logger::log('error', "Sync failed for product $product_id", [
                'error' => $result->get_error_message()
            ]);
            return $result;
        }

        // Save listing ID
        $new_listing_id = $result['id'] ?? $listing_id;
        update_post_meta($product_id, '_zicer_listing_id', $new_listing_id);
        update_post_meta($product_id, '_zicer_last_sync', current_time('mysql'));
        delete_post_meta($product_id, '_zicer_sync_error');

        // Sync images
        self::sync_images($product, $new_listing_id);

        Zicer_Logger::log('info', "Product $product_id synced", [
            'listing_id' => $new_listing_id,
            'action' => $listing_id ? 'update' : 'create'
        ]);

        return $result;
    }

    public static function delete_listing($product_id, $listing_id = null) {
        if (!$listing_id) {
            $listing_id = get_post_meta($product_id, '_zicer_listing_id', true);
        }

        if (!$listing_id) {
            return new WP_Error('no_listing', 'No listing to delete');
        }

        $api = Zicer_API_Client::instance();
        $result = $api->delete_listing($listing_id);

        if (!is_wp_error($result)) {
            delete_post_meta($product_id, '_zicer_listing_id');
            delete_post_meta($product_id, '_zicer_last_sync');
            Zicer_Logger::log('info', "Listing deleted for product $product_id", [
                'listing_id' => $listing_id
            ]);
        }

        return $result;
    }

    public static function is_product_available($product) {
        $threshold = (int) get_option('zicer_stock_threshold', 0);

        // If not managing stock, use stock status
        if (!$product->managing_stock()) {
            return $product->is_in_stock();
        }

        $quantity = $product->get_stock_quantity();
        return $quantity > $threshold;
    }

    public static function build_listing_data($product) {
        // Get category
        $wc_categories = $product->get_category_ids();
        $zicer_category = null;

        foreach ($wc_categories as $cat_id) {
            $zicer_category = Zicer_Category_Map::get_zicer_category($cat_id);
            if ($zicer_category) break;
        }

        if (!$zicer_category) {
            return new WP_Error('no_category', __('Nema mapirane ZICER kategorije', 'zicer-woo-sync'));
        }

        // Get region/city
        $region = get_option('zicer_default_region');
        $city = get_option('zicer_default_city');

        if (!$region || !$city) {
            return new WP_Error('no_location', __('Regija i grad nisu konfigurisani', 'zicer-woo-sync'));
        }

        // Build title
        $title = $product->get_name();
        if (get_option('zicer_truncate_title', '0') === '1') {
            $max_length = (int) get_option('zicer_title_max_length', 65);
            if (mb_strlen($title) > $max_length) {
                $title = mb_substr($title, 0, $max_length - 3) . '...';
            }
        }

        // Build description
        $description = self::build_description($product);

        // Get price
        $price = (float) $product->get_price();
        $conversion = (float) get_option('zicer_price_conversion', 1);
        $price = (int) round($price * $conversion);

        // Get condition
        $condition = get_post_meta($product->get_id(), '_zicer_condition', true);
        if (!$condition) {
            $condition = get_option('zicer_default_condition', 'Novo');
        }

        $data = [
            'title' => $title,
            'description' => $description,
            'shortDescription' => wp_trim_words(strip_tags($product->get_short_description()), 30),
            'sku' => $product->get_sku(),
            'price' => $price,
            'condition' => $condition,
            'type' => 'Prodaja',
            'isActive' => true,
            'isAvailable' => self::is_product_available($product),
            'category' => "/api/categories/$zicer_category",
            'region' => "/api/regions/$region",
            'city' => "/api/cities/$city",
        ];

        return $data;
    }

    public static function build_description($product) {
        $mode = get_option('zicer_description_mode', 'product');
        $template = get_option('zicer_description_template', '');
        $product_desc = $product->get_description();

        // Process template variables
        $connection = get_option('zicer_connection_status', []);
        $variables = [
            '{product_name}' => $product->get_name(),
            '{product_price}' => $product->get_price(),
            '{product_sku}' => $product->get_sku(),
            '{shop_name}' => $connection['shop'] ?? '',
        ];
        $template = str_replace(array_keys($variables), array_values($variables), $template);

        switch ($mode) {
            case 'replace':
                return $template;
            case 'prepend':
                return $template . "\n\n" . $product_desc;
            case 'append':
                return $product_desc . "\n\n" . $template;
            default:
                return $product_desc;
        }
    }

    public static function sync_images($product, $listing_id) {
        $sync_mode = get_option('zicer_sync_images', 'all');
        if ($sync_mode === 'none') return;

        $max_images = (int) get_option('zicer_max_images', 10);
        $api = Zicer_API_Client::instance();

        $image_ids = [];

        if ($sync_mode === 'featured') {
            $featured_id = $product->get_image_id();
            if ($featured_id) {
                $image_ids[] = $featured_id;
            }
        } else {
            // Get featured + gallery
            $featured_id = $product->get_image_id();
            if ($featured_id) {
                $image_ids[] = $featured_id;
            }
            $gallery_ids = $product->get_gallery_image_ids();
            $image_ids = array_merge($image_ids, $gallery_ids);
        }

        // Limit images
        $image_ids = array_slice($image_ids, 0, $max_images);

        // Get already synced images
        $synced_images = get_post_meta($product->get_id(), '_zicer_synced_images', true) ?: [];

        $position = 0;
        foreach ($image_ids as $image_id) {
            // Skip if already synced (by checking hash)
            $file_path = get_attached_file($image_id);
            if (!$file_path || !file_exists($file_path)) continue;

            $hash = md5_file($file_path);
            if (isset($synced_images[$image_id]) && $synced_images[$image_id] === $hash) {
                $position++;
                continue;
            }

            // Upload image
            $result = $api->upload_media($listing_id, $file_path, $position);
            if (!is_wp_error($result)) {
                $synced_images[$image_id] = $hash;
            }

            $position++;
        }

        update_post_meta($product->get_id(), '_zicer_synced_images', $synced_images);
    }

    // AJAX handlers
    public static function ajax_sync_product() {
        check_ajax_referer('zicer_admin', 'nonce');

        $product_id = (int) $_POST['product_id'];
        $result = self::sync_product($product_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public static function ajax_delete_listing() {
        check_ajax_referer('zicer_admin', 'nonce');

        $product_id = (int) $_POST['product_id'];
        $result = self::delete_listing($product_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    public static function ajax_bulk_sync() {
        check_ajax_referer('zicer_admin', 'nonce');

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_zicer_exclude',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];

        $product_ids = get_posts($args);

        foreach ($product_ids as $product_id) {
            Zicer_Queue::add($product_id, 'sync');
        }

        wp_send_json_success([
            'queued' => count($product_ids)
        ]);
    }

    public static function save_category_mapping() {
        if (!wp_verify_nonce($_POST['zicer_nonce'], 'zicer_category_mapping')) {
            wp_die('Security check failed');
        }

        $mapping = [];
        if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
            foreach ($_POST['mapping'] as $wc_id => $zicer_id) {
                if (!empty($zicer_id)) {
                    $mapping[(int) $wc_id] = sanitize_text_field($zicer_id);
                }
            }
        }

        Zicer_Category_Map::save_mapping($mapping);

        wp_redirect(admin_url('admin.php?page=zicer-categories&saved=1'));
        exit;
    }
}
```

### Success Criteria Phase 4:

#### Automated:
- [ ] Product meta saves correctly
- [ ] Sync creates listing in ZICER

#### Manual:
- [ ] Product meta box displays sync status
- [ ] Manual sync button works
- [ ] Images upload to ZICER

---

## Phase 5: Queue & Background Processing

### 5.1 Queue Class

**File**: `includes/class-zicer-queue.php`

```php
<?php
class Zicer_Queue {
    const MAX_ATTEMPTS = 3;

    public static function init() {
        add_action('zicer_process_queue', [__CLASS__, 'process']);
    }

    public static function add($product_id, $action, $meta = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        // Check for existing pending item
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE product_id = %d AND action = %s AND status = 'pending'",
            $product_id, $action
        ));

        if ($existing) return $existing;

        $wpdb->insert($table, [
            'product_id' => $product_id,
            'action' => $action,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);

        if (!empty($meta)) {
            update_post_meta($product_id, '_zicer_queue_meta', $meta);
        }

        return $wpdb->insert_id;
    }

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

    private static function process_item($item) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        // Mark as processing
        $wpdb->update($table, [
            'status' => 'processing',
            'attempts' => $item->attempts + 1
        ], ['id' => $item->id]);

        $result = null;

        switch ($item->action) {
            case 'sync':
                $result = Zicer_Sync::sync_product($item->product_id);
                break;

            case 'delete':
                $meta = get_post_meta($item->product_id, '_zicer_queue_meta', true);
                $listing_id = $meta['listing_id'] ?? null;
                $result = Zicer_Sync::delete_listing($item->product_id, $listing_id);
                delete_post_meta($item->product_id, '_zicer_queue_meta');
                break;
        }

        if (is_wp_error($result)) {
            $new_status = ($item->attempts + 1 >= self::MAX_ATTEMPTS) ? 'failed' : 'pending';
            $wpdb->update($table, [
                'status' => $new_status,
                'error_message' => $result->get_error_message(),
                'processed_at' => current_time('mysql')
            ], ['id' => $item->id]);
        } else {
            $wpdb->update($table, [
                'status' => 'completed',
                'processed_at' => current_time('mysql')
            ], ['id' => $item->id]);
        }
    }

    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';

        return [
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"),
            'processing' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'processing'"),
            'completed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'completed'"),
            'failed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'"),
        ];
    }

    public static function clear_failed() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';
        $wpdb->delete($table, ['status' => 'failed']);
    }

    public static function retry_failed() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_queue';
        $wpdb->update($table, [
            'status' => 'pending',
            'attempts' => 0,
            'error_message' => null
        ], ['status' => 'failed']);
    }
}
```

### 5.2 Logger Class

**File**: `includes/class-zicer-logger.php`

```php
<?php
class Zicer_Logger {
    public static function log($level, $message, $context = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_log';

        $wpdb->insert($table, [
            'product_id' => $context['product_id'] ?? null,
            'listing_id' => $context['listing_id'] ?? null,
            'action' => $context['action'] ?? $level,
            'status' => $level,
            'message' => $message . (!empty($context) ? ' | ' . json_encode($context) : ''),
            'created_at' => current_time('mysql')
        ]);

        // Also log to WP debug if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[ZICER $level] $message");
        }
    }

    public static function get_logs($limit = 100, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_log';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }

    public static function clear_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'zicer_sync_log';
        $wpdb->query("TRUNCATE TABLE $table");
    }
}
```

### 5.3 Status Page View

**File**: `admin/views/sync-status.php`

```php
<?php if (!defined('ABSPATH')) exit;
$stats = Zicer_Queue::get_stats();
$rate_limit = Zicer_API_Client::instance()->get_rate_limit_status();
?>

<div class="wrap zicer-status">
    <h1><?php _e('Status sinhronizacije', 'zicer-woo-sync'); ?></h1>

    <div class="zicer-stats-grid">
        <div class="zicer-stat-card">
            <span class="stat-number"><?php echo $stats['pending']; ?></span>
            <span class="stat-label"><?php _e('Na čekanju', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card">
            <span class="stat-number"><?php echo $stats['processing']; ?></span>
            <span class="stat-label"><?php _e('U obradi', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card success">
            <span class="stat-number"><?php echo $stats['completed']; ?></span>
            <span class="stat-label"><?php _e('Završeno', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card error">
            <span class="stat-number"><?php echo $stats['failed']; ?></span>
            <span class="stat-label"><?php _e('Neuspješno', 'zicer-woo-sync'); ?></span>
        </div>
    </div>

    <div class="zicer-card">
        <h2><?php _e('API Rate Limit', 'zicer-woo-sync'); ?></h2>
        <p>
            <?php printf(
                __('Preostalo zahtjeva: %d | Reset za: %d sekundi', 'zicer-woo-sync'),
                $rate_limit['remaining'],
                $rate_limit['reset_in']
            ); ?>
        </p>
    </div>

    <div class="zicer-card">
        <h2><?php _e('Akcije', 'zicer-woo-sync'); ?></h2>

        <p>
            <button type="button" id="zicer-bulk-sync" class="button button-primary">
                <?php _e('Pokreni bulk sinhronizaciju', 'zicer-woo-sync'); ?>
            </button>
            <span class="description">
                <?php _e('Dodaje sve proizvode u red za sinhronizaciju', 'zicer-woo-sync'); ?>
            </span>
        </p>

        <?php if ($stats['failed'] > 0): ?>
            <p>
                <button type="button" id="zicer-retry-failed" class="button">
                    <?php _e('Ponovi neuspješne', 'zicer-woo-sync'); ?>
                </button>
                <button type="button" id="zicer-clear-failed" class="button">
                    <?php _e('Obriši neuspješne', 'zicer-woo-sync'); ?>
                </button>
            </p>
        <?php endif; ?>
    </div>

    <div class="zicer-card">
        <h2><?php _e('Sinhronizovani proizvodi', 'zicer-woo-sync'); ?></h2>
        <?php
        $synced_products = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 20,
            'meta_query' => [
                ['key' => '_zicer_listing_id', 'compare' => 'EXISTS']
            ]
        ]);

        if ($synced_products->have_posts()): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Proizvod', 'zicer-woo-sync'); ?></th>
                        <th><?php _e('ZICER ID', 'zicer-woo-sync'); ?></th>
                        <th><?php _e('Zadnja sync', 'zicer-woo-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($synced_products->have_posts()): $synced_products->the_post(); ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </td>
                            <td>
                                <?php
                                $lid = get_post_meta(get_the_ID(), '_zicer_listing_id', true);
                                echo esc_html(substr($lid, 0, 8)) . '...';
                                ?>
                            </td>
                            <td><?php echo get_post_meta(get_the_ID(), '_zicer_last_sync', true); ?></td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('Nema sinhronizovanih proizvoda.', 'zicer-woo-sync'); ?></p>
        <?php endif; ?>
    </div>
</div>
```

### Success Criteria Phase 5:

#### Automated:
- [ ] Queue processes items correctly
- [ ] Cron job runs every minute

#### Manual:
- [ ] Status page shows correct stats
- [ ] Bulk sync adds items to queue
- [ ] Failed items can be retried

---

## Phase 6: Admin Assets

### 6.1 Admin CSS

**File**: `admin/css/admin.css`

```css
.zicer-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.zicer-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

/* Connection status */
.zicer-connection-status.connected { color: #46b450; }
.zicer-connection-status.disconnected { color: #dc3232; }

/* Product meta box */
.zicer-product-meta .zicer-status {
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
}
.zicer-product-meta .zicer-status.synced { background: #d4edda; color: #155724; }
.zicer-product-meta .zicer-status.error { background: #f8d7da; color: #721c24; }
.zicer-product-meta .zicer-status.pending { background: #fff3cd; color: #856404; }
.zicer-product-meta .zicer-status.excluded { background: #e2e3e5; color: #383d41; }

/* Stats grid */
.zicer-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
.zicer-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    text-align: center;
}
.zicer-stat-card .stat-number {
    display: block;
    font-size: 36px;
    font-weight: 600;
}
.zicer-stat-card .stat-label {
    color: #666;
}
.zicer-stat-card.success .stat-number { color: #46b450; }
.zicer-stat-card.error .stat-number { color: #dc3232; }

/* Category mapping */
.zicer-categories .zicer-category-select { width: 100%; max-width: 400px; }
.zicer-categories .spinner { float: none; margin: 0 5px; }
```

### 6.2 Admin JavaScript

**File**: `admin/js/admin.js`

```javascript
jQuery(document).ready(function($) {
    // Test connection
    $('#zicer-test-connection').on('click', function() {
        var $btn = $(this);
        var token = $('#zicer_api_token').val();

        $btn.prop('disabled', true).text(zicerAdmin.strings.testing);

        $.post(zicerAdmin.ajaxUrl, {
            action: 'zicer_test_connection',
            nonce: zicerAdmin.nonce,
            token: token
        }, function(response) {
            $btn.prop('disabled', false).text('Testiraj konekciju');
            if (response.success) {
                $('#zicer-connection-status')
                    .removeClass('disconnected')
                    .addClass('connected')
                    .html('✓ ' + zicerAdmin.strings.connected);
                location.reload();
            } else {
                alert(zicerAdmin.strings.error + ' ' + response.data);
            }
        });
    });

    // Sync product
    $('.zicer-sync-now').on('click', function() {
        var $btn = $(this);
        var productId = $btn.data('product-id');

        $btn.prop('disabled', true).text(zicerAdmin.strings.syncing);

        $.post(zicerAdmin.ajaxUrl, {
            action: 'zicer_sync_product',
            nonce: zicerAdmin.nonce,
            product_id: productId
        }, function(response) {
            location.reload();
        });
    });

    // Delete listing
    $('.zicer-delete-listing').on('click', function() {
        if (!confirm('Da li ste sigurni?')) return;

        var $btn = $(this);
        var productId = $btn.data('product-id');

        $.post(zicerAdmin.ajaxUrl, {
            action: 'zicer_delete_listing',
            nonce: zicerAdmin.nonce,
            product_id: productId
        }, function(response) {
            location.reload();
        });
    });

    // Bulk sync
    $('#zicer-bulk-sync').on('click', function() {
        if (!confirm(zicerAdmin.strings.confirm_bulk)) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text(zicerAdmin.strings.syncing);

        $.post(zicerAdmin.ajaxUrl, {
            action: 'zicer_bulk_sync',
            nonce: zicerAdmin.nonce
        }, function(response) {
            if (response.success) {
                alert('Dodano ' + response.data.queued + ' proizvoda u red.');
                location.reload();
            }
        });
    });

    // Load categories
    $('#zicer-load-categories').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Učitavanje...');

        $.post(zicerAdmin.ajaxUrl, {
            action: 'zicer_fetch_categories',
            nonce: zicerAdmin.nonce
        }, function(response) {
            location.reload();
        });
    });

    // Category suggestions
    $('.zicer-suggest-category').on('click', function() {
        var $btn = $(this);
        var termName = $btn.data('term-name');
        var termId = $btn.data('term-id');
        var $select = $('select[name="mapping[' + termId + ']"]');
        var $spinner = $btn.next('.spinner');

        $spinner.addClass('is-active');

        $.post(zicerAdmin.ajaxUrl, {
            action: 'zicer_suggest_category',
            nonce: zicerAdmin.nonce,
            title: termName
        }, function(response) {
            $spinner.removeClass('is-active');
            if (response.success && response.data.length > 0) {
                var suggestion = response.data[0];
                $select.val(suggestion.uuid);
            }
        });
    });

    // Refresh regions
    $('#zicer-refresh-regions').on('click', function() {
        $.post(zicerAdmin.ajaxUrl, {
            action: 'zicer_fetch_regions',
            nonce: zicerAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    });
});
```

### Success Criteria Phase 6:

#### Automated:
- [ ] CSS/JS files load without errors

#### Manual:
- [ ] All buttons work correctly
- [ ] AJAX calls complete successfully

---

## Testing Strategy

### Unit Tests:
- API client request/response handling
- Rate limit tracking
- Category mapping lookup
- Description template processing

### Integration Tests:
- Full sync flow (create, update, delete)
- Image upload handling
- Queue processing

### Manual Testing Steps:
1. Install plugin on WordPress with WooCommerce
2. Configure API token and verify connection
3. Set up region/city defaults
4. Map at least one WooCommerce category
5. Create a test product and verify it syncs
6. Modify product price and verify update
7. Delete product and verify removal from ZICER
8. Test bulk sync with multiple products
9. Verify rate limiting doesn't cause failures

---

## References

- ZICER API Documentation: `../zicer.apidoc/data/`
- WooCommerce Hooks: https://woocommerce.github.io/code-reference/hooks/hooks.html
- WordPress Settings API: https://developer.wordpress.org/plugins/settings/
