<?php
/**
 * ZICER Sync
 *
 * Core synchronization logic for WooCommerce products to ZICER listings.
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Zicer_Sync
 */
class Zicer_Sync {

    /**
     * Initialize sync
     */
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
        add_action('wp_ajax_zicer_clear_stale', [__CLASS__, 'ajax_clear_stale']);

        // Category mapping save
        add_action('admin_post_zicer_save_category_mapping', [__CLASS__, 'save_category_mapping']);
    }

    /**
     * Handle product save
     *
     * @param int        $product_id The product ID.
     * @param WC_Product $product    The product object.
     */
    public static function on_product_save($product_id, $product = null) {
        if (!get_option('zicer_realtime_sync', '1')) {
            return;
        }
        if (get_post_meta($product_id, '_zicer_exclude', true) === 'yes') {
            return;
        }

        if (!$product) {
            $product = wc_get_product($product_id);
        }
        if (!$product) {
            return;
        }

        // Only auto-sync if product has a ZICER category (override or mapped)
        $has_category = get_post_meta($product_id, '_zicer_category', true);
        if (!$has_category) {
            foreach ($product->get_category_ids() as $cat_id) {
                if (Zicer_Category_Map::get_zicer_category($cat_id)) {
                    $has_category = true;
                    break;
                }
            }
        }
        if (!$has_category) {
            return; // Skip silently - product not configured for ZICER
        }

        // Sync immediately (don't rely on cron)
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                self::sync_product($variation_id);
            }
        } else {
            self::sync_product($product_id);
        }
    }

    /**
     * Handle product delete
     *
     * @param int $post_id The post ID.
     */
    public static function on_product_delete($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $listing_id = get_post_meta($post_id, '_zicer_listing_id', true);
        if ($listing_id) {
            // Delete immediately from ZICER
            self::delete_listing($post_id, $listing_id);
        }
    }

    /**
     * Handle stock change
     *
     * @param WC_Product $product The product object.
     */
    public static function on_stock_change($product) {
        if (!get_option('zicer_realtime_sync', '1')) {
            return;
        }
        if (get_post_meta($product->get_id(), '_zicer_exclude', true) === 'yes') {
            return;
        }

        $listing_id = get_post_meta($product->get_id(), '_zicer_listing_id', true);
        if ($listing_id) {
            Zicer_Queue::add($product->get_id(), 'sync');
        }
    }

    /**
     * Sync a product to ZICER
     *
     * @param int $product_id The product ID.
     * @return array|WP_Error
     */
    public static function sync_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('invalid_product', 'Product not found');
        }

        // For variable products, sync each variation instead
        if ($product->is_type('variable')) {
            $results = [];
            foreach ($product->get_children() as $variation_id) {
                $results[$variation_id] = self::sync_product($variation_id);
            }
            return $results;
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
            // Create new - clear synced images so they get re-uploaded
            delete_post_meta($product_id, '_zicer_synced_images');
            $result = $api->create_listing($data);
        }

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $error_msg  = $result->get_error_message();

            // Mark 404 errors specially so UI can show "Clear & Re-create" option
            if (isset($error_data['status']) && $error_data['status'] === 404) {
                $error_msg = '404:' . __('Listing not found on ZICER (may have been deleted)', 'zicer-woo-sync');
            }

            update_post_meta($product_id, '_zicer_sync_error', $error_msg);
            Zicer_Logger::log('error', "Sync failed for product $product_id", [
                'error' => $result->get_error_message(),
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
            'action'     => $listing_id ? 'update' : 'create',
        ]);

        return $result;
    }

    /**
     * Delete a listing from ZICER
     *
     * @param int    $product_id The product ID.
     * @param string $listing_id Optional listing ID.
     * @return array|WP_Error
     */
    public static function delete_listing($product_id, $listing_id = null) {
        $product = wc_get_product($product_id);

        // For variable products, delete each variation's listing
        if ($product && $product->is_type('variable')) {
            $results = [];
            foreach ($product->get_children() as $variation_id) {
                $results[$variation_id] = self::delete_listing($variation_id);
            }
            return $results;
        }

        if (!$listing_id) {
            $listing_id = get_post_meta($product_id, '_zicer_listing_id', true);
        }

        if (!$listing_id) {
            return new WP_Error('no_listing', 'No listing to delete');
        }

        $api    = Zicer_API_Client::instance();
        $result = $api->delete_listing($listing_id);

        if (!is_wp_error($result)) {
            delete_post_meta($product_id, '_zicer_listing_id');
            delete_post_meta($product_id, '_zicer_last_sync');
            delete_post_meta($product_id, '_zicer_synced_images');
            Zicer_Logger::log('info', "Listing deleted for product $product_id", [
                'listing_id' => $listing_id,
            ]);
        }

        return $result;
    }

    /**
     * Check if product is available for sync
     *
     * @param WC_Product $product The product object.
     * @return bool
     */
    public static function is_product_available($product) {
        $threshold = (int) get_option('zicer_stock_threshold', 0);

        // If not managing stock, use stock status
        if (!$product->managing_stock()) {
            return $product->is_in_stock();
        }

        $quantity = $product->get_stock_quantity();
        return $quantity > $threshold;
    }

    /**
     * Build listing data from product
     *
     * @param WC_Product $product The product object.
     * @return array|WP_Error
     */
    public static function build_listing_data($product) {
        // Get category - check for product-level override first
        $zicer_category = get_post_meta($product->get_id(), '_zicer_category', true);

        // For variations, check parent's category override
        if (!$zicer_category && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $zicer_category = get_post_meta($parent_id, '_zicer_category', true);
        }

        // Fall back to category mapping
        if (!$zicer_category) {
            // Variations don't have WC categories - use parent's
            if ($product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                $wc_categories = $parent ? $parent->get_category_ids() : [];
            } else {
                $wc_categories = $product->get_category_ids();
            }

            foreach ($wc_categories as $cat_id) {
                $zicer_category = Zicer_Category_Map::get_zicer_category($cat_id);
                if ($zicer_category) {
                    break;
                }
            }
        }

        if (!$zicer_category) {
            return new WP_Error('no_category', __('No mapped ZICER category', 'zicer-woo-sync'));
        }

        // Get region/city
        $region = get_option('zicer_default_region');
        $city   = get_option('zicer_default_city');

        if (!$region || !$city) {
            return new WP_Error('no_location', __('Region and city are not configured', 'zicer-woo-sync'));
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
        $price      = (float) $product->get_price();
        $conversion = (float) get_option('zicer_price_conversion', 1);
        $price      = (int) round($price * $conversion);

        // Get condition
        $condition = get_post_meta($product->get_id(), '_zicer_condition', true);
        if (!$condition) {
            $condition = get_option('zicer_default_condition', 'Novo');
        }

        $data = [
            'title'            => $title,
            'description'      => $description,
            'shortDescription' => wp_trim_words(wp_strip_all_tags($product->get_short_description()), 30),
            'sku'              => $product->get_sku(),
            'price'            => $price,
            'condition'        => $condition,
            'type'             => 'Prodaja',
            'isActive'         => true,
            'isAvailable'      => self::is_product_available($product),
            'category'         => "/api/categories/$zicer_category",
            'region'           => "/api/regions/$region",
            'city'             => "/api/cities/$city",
        ];

        return $data;
    }

    /**
     * Build description based on settings
     *
     * @param WC_Product $product The product object.
     * @return string
     */
    public static function build_description($product) {
        $mode         = get_option('zicer_description_mode', 'product');
        $template     = get_option('zicer_description_template', '');
        $product_desc = $product->get_description();

        // Process template variables
        $connection = get_option('zicer_connection_status', []);
        $variables  = [
            '{product_name}'  => $product->get_name(),
            '{product_price}' => $product->get_price(),
            '{product_sku}'   => $product->get_sku(),
            '{shop_name}'     => $connection['shop'] ?? '',
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

    /**
     * Sync product images to ZICER
     *
     * @param WC_Product $product    The product object.
     * @param string     $listing_id The listing ID.
     */
    public static function sync_images($product, $listing_id) {
        $sync_mode = get_option('zicer_sync_images', 'all');
        if ($sync_mode === 'none') {
            return;
        }

        $max_images = (int) get_option('zicer_max_images', 10);
        $api        = Zicer_API_Client::instance();

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
            $image_ids   = array_merge($image_ids, $gallery_ids);
        }

        // Limit images
        $image_ids = array_slice($image_ids, 0, $max_images);

        // Get already synced images
        $synced_images = get_post_meta($product->get_id(), '_zicer_synced_images', true);
        if (!is_array($synced_images)) {
            $synced_images = [];
        }

        $position = 0;
        foreach ($image_ids as $image_id) {
            // Skip if already synced (by checking hash)
            $file_path = get_attached_file($image_id);
            if (!$file_path || !file_exists($file_path)) {
                continue;
            }

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

    /**
     * AJAX: Sync single product
     */
    public static function ajax_sync_product() {
        check_ajax_referer('zicer_admin', 'nonce');

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $result     = self::sync_product($product_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Delete listing
     */
    public static function ajax_delete_listing() {
        check_ajax_referer('zicer_admin', 'nonce');

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $result     = self::delete_listing($product_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Clear stale listing data (when listing was deleted on ZICER)
     */
    public static function ajax_clear_stale() {
        check_ajax_referer('zicer_admin', 'nonce');

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        // Clear all ZICER sync data
        delete_post_meta($product_id, '_zicer_listing_id');
        delete_post_meta($product_id, '_zicer_last_sync');
        delete_post_meta($product_id, '_zicer_sync_error');
        delete_post_meta($product_id, '_zicer_synced_images');

        Zicer_Logger::log('info', "Cleared stale ZICER data for product $product_id");

        wp_send_json_success();
    }

    /**
     * AJAX: Bulk sync all products
     */
    public static function ajax_bulk_sync() {
        check_ajax_referer('zicer_admin', 'nonce');

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_zicer_exclude',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];

        $product_ids = get_posts($args);

        // Also include products where _zicer_exclude is 'no'
        $args['meta_query'] = [
            [
                'key'   => '_zicer_exclude',
                'value' => 'no',
            ],
        ];
        $product_ids = array_merge($product_ids, get_posts($args));
        $product_ids = array_unique($product_ids);

        foreach ($product_ids as $product_id) {
            Zicer_Queue::add($product_id, 'sync');
        }

        wp_send_json_success([
            'queued' => count($product_ids),
        ]);
    }

    /**
     * Handle category mapping form save
     */
    public static function save_category_mapping() {
        if (!isset($_POST['zicer_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zicer_nonce'])), 'zicer_category_mapping')) {
            wp_die('Security check failed');
        }

        $mapping = [];
        if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
            foreach ($_POST['mapping'] as $wc_id => $zicer_id) {
                $zicer_id = sanitize_text_field(wp_unslash($zicer_id));
                if (!empty($zicer_id)) {
                    $mapping[(int) $wc_id] = $zicer_id;
                }
            }
        }

        Zicer_Category_Map::save_mapping($mapping);

        // Save fallback category
        if (isset($_POST['fallback_category'])) {
            $fallback = sanitize_text_field(wp_unslash($_POST['fallback_category']));
            update_option('zicer_fallback_category', $fallback);
        }

        wp_redirect(admin_url('admin.php?page=zicer-categories&saved=1'));
        exit;
    }
}
