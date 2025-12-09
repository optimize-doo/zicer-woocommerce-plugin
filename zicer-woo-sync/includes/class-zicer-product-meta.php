<?php
/**
 * ZICER Product Meta
 *
 * Handles product meta box and per-product ZICER settings.
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Zicer_Product_Meta
 */
class Zicer_Product_Meta {

    /**
     * Initialize product meta
     */
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_meta']);
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_general_fields']);

        // Product list columns
        add_filter('manage_edit-product_columns', [__CLASS__, 'add_product_column'], 20);
        add_action('manage_product_posts_custom_column', [__CLASS__, 'render_product_column'], 10, 2);

        // Product list filter
        add_action('restrict_manage_posts', [__CLASS__, 'add_sync_filter']);
        add_filter('parse_query', [__CLASS__, 'filter_by_sync_status']);

        // Bulk actions
        add_filter('bulk_actions-edit-product', [__CLASS__, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-product', [__CLASS__, 'handle_bulk_actions'], 10, 3);
        add_action('admin_notices', [__CLASS__, 'bulk_action_notices']);
    }

    /**
     * Add ZICER column to product list
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_product_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            // Add after featured (star) column
            if ($key === 'featured') {
                $new_columns['zicer_sync'] = __('Zicer', 'zicer-woo-sync');
            }
        }
        return $new_columns;
    }

    /**
     * Render ZICER column content
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public static function render_product_column($column, $post_id) {
        if ($column !== 'zicer_sync') {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        $listing_id = null;

        if ($product->is_type('variable')) {
            // Get first synced variation's listing ID
            foreach ($product->get_children() as $variation_id) {
                $var_listing = get_post_meta($variation_id, '_zicer_listing_id', true);
                if ($var_listing) {
                    $listing_id = $var_listing;
                    break;
                }
            }
        } else {
            $listing_id = get_post_meta($post_id, '_zicer_listing_id', true);
        }

        if ($listing_id) {
            printf(
                '<a href="https://zicer.ba/oglasi/%s" target="_blank" title="%s" class="zicer-external-link"><span class="dashicons dashicons-external"></span></a>',
                esc_attr($listing_id),
                esc_attr__('View on ZICER', 'zicer-woo-sync')
            );
        }
    }

    /**
     * Add ZICER sync filter dropdown to product list
     */
    public static function add_sync_filter() {
        global $typenow;

        if ($typenow !== 'product') {
            return;
        }

        $current = isset($_GET['zicer_sync']) ? sanitize_text_field($_GET['zicer_sync']) : '';
        ?>
        <select name="zicer_sync">
            <option value=""><?php esc_html_e('ZICER sync', 'zicer-woo-sync'); ?></option>
            <option value="synced" <?php selected($current, 'synced'); ?>><?php esc_html_e('Synced', 'zicer-woo-sync'); ?></option>
            <option value="not_synced" <?php selected($current, 'not_synced'); ?>><?php esc_html_e('Not synced', 'zicer-woo-sync'); ?></option>
        </select>
        <?php
    }

    /**
     * Filter products by ZICER sync status
     *
     * @param WP_Query $query The query object.
     */
    public static function filter_by_sync_status($query) {
        global $pagenow, $typenow, $wpdb;

        if ($pagenow !== 'edit.php' || $typenow !== 'product' || !$query->is_main_query()) {
            return;
        }

        if (empty($_GET['zicer_sync'])) {
            return;
        }

        $filter = sanitize_text_field($_GET['zicer_sync']);

        // Get IDs of synced simple products
        $synced_simple = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_zicer_listing_id'
             AND p.post_type = 'product'"
        );

        // Get parent IDs of synced variations
        $synced_variable = $wpdb->get_col(
            "SELECT DISTINCT p.post_parent FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_zicer_listing_id'
             AND p.post_type = 'product_variation'
             AND p.post_parent > 0"
        );

        $synced_ids = array_unique(array_merge($synced_simple, $synced_variable));

        if ($filter === 'synced') {
            if (!empty($synced_ids)) {
                $query->set('post__in', $synced_ids);
            } else {
                // No synced products - return empty
                $query->set('post__in', [0]);
            }
        } elseif ($filter === 'not_synced') {
            if (!empty($synced_ids)) {
                $query->set('post__not_in', $synced_ids);
            }
        }
    }

    /**
     * Add ZICER bulk actions
     *
     * @param array $actions Existing bulk actions.
     * @return array Modified bulk actions.
     */
    public static function add_bulk_actions($actions) {
        $actions['zicer_sync'] = __('Queue: Add to ZICER', 'zicer-woo-sync');
        $actions['zicer_remove'] = __('Queue: Remove from ZICER', 'zicer-woo-sync');
        return $actions;
    }

    /**
     * Handle ZICER bulk actions
     *
     * @param string $redirect_url Redirect URL.
     * @param string $action       Action name.
     * @param array  $post_ids     Selected post IDs.
     * @return string Modified redirect URL.
     */
    public static function handle_bulk_actions($redirect_url, $action, $post_ids) {
        if (!in_array($action, ['zicer_sync', 'zicer_remove'], true)) {
            return $redirect_url;
        }

        $queued = 0;

        foreach ($post_ids as $post_id) {
            $product = wc_get_product($post_id);
            if (!$product) {
                continue;
            }

            if ($action === 'zicer_sync') {
                // For variable products, queue each variation
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        Zicer_Queue::add($variation_id, 'sync');
                        $queued++;
                    }
                } else {
                    Zicer_Queue::add($post_id, 'sync');
                    $queued++;
                }
            } elseif ($action === 'zicer_remove') {
                // For variable products, queue each variation for delete
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $listing_id = get_post_meta($variation_id, '_zicer_listing_id', true);
                        if ($listing_id) {
                            Zicer_Queue::add($variation_id, 'delete', ['listing_id' => $listing_id]);
                            $queued++;
                        }
                    }
                } else {
                    $listing_id = get_post_meta($post_id, '_zicer_listing_id', true);
                    if ($listing_id) {
                        Zicer_Queue::add($post_id, 'delete', ['listing_id' => $listing_id]);
                        $queued++;
                    }
                }
            }
        }

        $redirect_url = add_query_arg([
            'zicer_bulk_action' => $action,
            'zicer_queued' => $queued,
        ], $redirect_url);

        return $redirect_url;
    }

    /**
     * Display bulk action admin notices
     */
    public static function bulk_action_notices() {
        if (empty($_GET['zicer_bulk_action']) || empty($_GET['zicer_queued'])) {
            return;
        }

        $action = sanitize_text_field($_GET['zicer_bulk_action']);
        $queued = (int) $_GET['zicer_queued'];

        if ($action === 'zicer_sync') {
            $message = sprintf(
                /* translators: %d: number of items */
                _n('%d item added to sync queue.', '%d items added to sync queue.', $queued, 'zicer-woo-sync'),
                $queued
            );
        } elseif ($action === 'zicer_remove') {
            $message = sprintf(
                /* translators: %d: number of items */
                _n('%d item added to removal queue.', '%d items added to removal queue.', $queued, 'zicer-woo-sync'),
                $queued
            );
        } else {
            return;
        }

        printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
    }

    /**
     * Add meta box to product edit page
     */
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

    /**
     * Add fields to general product data tab
     */
    public static function add_general_fields() {
        global $post;

        woocommerce_wp_select([
            'id'          => '_zicer_condition',
            'label'       => __('ZICER Condition', 'zicer-woo-sync'),
            'options'     => array_merge(
                ['' => __('-- Use default --', 'zicer-woo-sync')],
                Zicer_Settings::get_conditions()
            ),
            'desc_tip'    => true,
            'description' => __('Product condition for ZICER listing', 'zicer-woo-sync'),
        ]);

        woocommerce_wp_checkbox([
            'id'          => '_zicer_exclude',
            'label'       => __('Exclude from ZICER sync', 'zicer-woo-sync'),
            'description' => __('Do not sync this product with ZICER', 'zicer-woo-sync'),
        ]);
    }

    /**
     * Render meta box content
     *
     * @param WP_Post $post The post object.
     */
    public static function render_meta_box($post) {
        $product           = wc_get_product($post->ID);
        $is_variable       = $product && $product->is_type('variable');
        $listing_id        = get_post_meta($post->ID, '_zicer_listing_id', true);
        $last_sync         = get_post_meta($post->ID, '_zicer_last_sync', true);
        $sync_error        = get_post_meta($post->ID, '_zicer_sync_error', true);
        $excluded          = get_post_meta($post->ID, '_zicer_exclude', true);
        $category_override = get_post_meta($post->ID, '_zicer_category', true);

        // For variable products, check variations' sync status
        $synced_variations = 0;
        $total_variations  = 0;
        if ($is_variable) {
            $children = $product->get_children();
            $total_variations = count($children);
            foreach ($children as $variation_id) {
                if (get_post_meta($variation_id, '_zicer_listing_id', true)) {
                    $synced_variations++;
                }
                // Get last sync time from variations
                $var_sync = get_post_meta($variation_id, '_zicer_last_sync', true);
                if ($var_sync && (!$last_sync || $var_sync > $last_sync)) {
                    $last_sync = $var_sync;
                }
            }
            // Consider variable product "synced" if all variations are synced
            if ($synced_variations > 0) {
                $listing_id = 'variations';
            }
        }

        // Get ZICER categories for dropdown
        $zicer_categories = Zicer_Category_Map::get_cached_categories();
        $flat_categories  = $zicer_categories ? Zicer_Category_Map::flatten_categories($zicer_categories) : [];

        // Resolve the effective category (override or mapped)
        $effective_category = $category_override;
        $is_from_mapping    = false;
        if (!$effective_category) {
            $product       = wc_get_product($post->ID);
            $wc_categories = $product ? $product->get_category_ids() : [];
            foreach ($wc_categories as $cat_id) {
                $mapped = Zicer_Category_Map::get_zicer_category($cat_id);
                if ($mapped) {
                    $effective_category = $mapped;
                    $is_from_mapping    = true;
                    break;
                }
            }
        }

        // Check current sync readiness
        $current_issue = self::check_sync_readiness($post->ID, $category_override);

        wp_nonce_field('zicer_product_meta', 'zicer_meta_nonce');
        ?>
        <div class="zicer-product-meta">
            <!-- ZICER Category -->
            <p>
                <label for="_zicer_category"><strong><?php esc_html_e('ZICER Category', 'zicer-woo-sync'); ?></strong></label>
            </p>
            <?php if (!empty($flat_categories)) : ?>
                <p>
                    <select name="_zicer_category"
                            id="_zicer_category"
                            class="zicer-select2"
                            style="width: 100%;"
                            data-mapped-category="<?php echo esc_attr($is_from_mapping ? $effective_category : ''); ?>">
                        <option value=""><?php esc_html_e('-- Select --', 'zicer-woo-sync'); ?></option>
                        <?php foreach ($flat_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat['id']); ?>"
                                    <?php selected($effective_category, $cat['id']); ?>
                                    <?php disabled(!empty($cat['disabled'])); ?>>
                                <?php echo esc_html($cat['path']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <?php if ($is_from_mapping) : ?>
                    <p class="description">
                        <?php esc_html_e('From category mapping. Change to override.', 'zicer-woo-sync'); ?>
                    </p>
                <?php elseif ($category_override) : ?>
                    <p class="description">
                        <?php esc_html_e('Manual override.', 'zicer-woo-sync'); ?>
                        <a href="#" class="zicer-clear-override"><?php esc_html_e('Use mapping instead', 'zicer-woo-sync'); ?></a>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <p class="description">
                    <?php esc_html_e('Load categories in ZICER settings first.', 'zicer-woo-sync'); ?>
                </p>
            <?php endif; ?>

            <hr>

            <!-- Status -->
            <?php
            $is_404_error = $sync_error && strpos($sync_error, '404:') === 0;
            ?>
            <?php if ($excluded === 'yes') : ?>
                <p class="zicer-status excluded">
                    <?php esc_html_e('Excluded from sync', 'zicer-woo-sync'); ?>
                </p>
            <?php elseif ($is_404_error) : ?>
                <p class="zicer-status warning">
                    &#9888; <?php esc_html_e('Listing not found', 'zicer-woo-sync'); ?>
                </p>
                <p class="zicer-error"><?php echo esc_html(substr($sync_error, 4)); ?></p>
                <p>
                    <button type="button"
                            class="button zicer-clear-stale"
                            style="width: 100%;"
                            data-product-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Clear & Re-create', 'zicer-woo-sync'); ?>
                    </button>
                </p>
            <?php elseif ($listing_id) : ?>
                <p class="zicer-status synced">
                    &#10003; <?php esc_html_e('Synced', 'zicer-woo-sync'); ?>
                    <?php if ($is_variable && $total_variations > 0) : ?>
                        (<?php printf(
                            /* translators: %1$d: synced count, %2$d: total count */
                            esc_html__('%1$d/%2$d variations', 'zicer-woo-sync'),
                            $synced_variations,
                            $total_variations
                        ); ?>)
                    <?php endif; ?>
                </p>
                <p class="zicer-info">
                    <?php if (!$is_variable) : ?>
                        <strong>ID:</strong> <?php echo esc_html(substr($listing_id, 0, 8)); ?>...
                        <br>
                    <?php endif; ?>
                    <strong><?php esc_html_e('Last sync:', 'zicer-woo-sync'); ?></strong>
                    <?php echo esc_html($last_sync); ?>
                </p>
                <?php if (!$is_variable) : ?>
                    <p>
                        <a href="https://zicer.ba/oglasi/<?php echo esc_attr($listing_id); ?>"
                           target="_blank" class="button">
                            <?php esc_html_e('View on ZICER', 'zicer-woo-sync'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php elseif ($current_issue) : ?>
                <p class="zicer-status error">
                    &#10007; <?php esc_html_e('Cannot sync', 'zicer-woo-sync'); ?>
                </p>
                <p class="zicer-error">
                    <?php if ($current_issue['type'] === 'no_category') : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=zicer-categories')); ?>">
                            <?php echo esc_html($current_issue['message']); ?>
                        </a>
                    <?php elseif ($current_issue['type'] === 'no_location') : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=zicer-settings')); ?>">
                            <?php echo esc_html($current_issue['message']); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html($current_issue['message']); ?>
                    <?php endif; ?>
                </p>
            <?php elseif ($sync_error) : ?>
                <p class="zicer-status warning">
                    &#9888; <?php esc_html_e('Last sync failed', 'zicer-woo-sync'); ?>
                </p>
                <p class="zicer-error"><?php echo esc_html($sync_error); ?></p>
            <?php else : ?>
                <p class="zicer-status pending">
                    <?php esc_html_e('Ready to sync', 'zicer-woo-sync'); ?>
                </p>
            <?php endif; ?>

            <hr>

            <!-- Actions -->
            <p>
                <button type="button"
                        class="button button-primary zicer-sync-now"
                        style="width: 100%;"
                        data-product-id="<?php echo esc_attr($post->ID); ?>"
                        <?php disabled((bool) $current_issue); ?>>
                    <?php esc_html_e('Sync Now', 'zicer-woo-sync'); ?>
                </button>
            </p>

            <?php if ($listing_id) : ?>
                <p>
                    <button type="button"
                            class="button zicer-delete-listing"
                            style="width: 100%;"
                            data-product-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Remove from ZICER', 'zicer-woo-sync'); ?>
                    </button>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save product meta
     *
     * @param int $post_id The post ID.
     */
    public static function save_meta($post_id) {
        if (!isset($_POST['zicer_meta_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zicer_meta_nonce'])), 'zicer_product_meta')) {
            return;
        }

        $condition = isset($_POST['_zicer_condition']) ?
                     sanitize_text_field(wp_unslash($_POST['_zicer_condition'])) : '';
        $exclude   = isset($_POST['_zicer_exclude']) ? 'yes' : 'no';
        $category  = isset($_POST['_zicer_category']) ?
                     sanitize_text_field(wp_unslash($_POST['_zicer_category'])) : '';

        // Only save category if it differs from mapped category (to allow mapping changes to propagate)
        $mapped_category = self::get_mapped_category($post_id);
        if ($category === $mapped_category) {
            $category = ''; // Clear override, use mapping
        }

        update_post_meta($post_id, '_zicer_condition', $condition);
        update_post_meta($post_id, '_zicer_exclude', $exclude);
        update_post_meta($post_id, '_zicer_category', $category);
    }

    /**
     * Get mapped ZICER category for a product
     *
     * @param int $product_id Product ID.
     * @return string|null ZICER category ID or null.
     */
    private static function get_mapped_category($product_id) {
        $product       = wc_get_product($product_id);
        $wc_categories = $product ? $product->get_category_ids() : [];

        foreach ($wc_categories as $cat_id) {
            $mapped = Zicer_Category_Map::get_zicer_category($cat_id);
            if ($mapped) {
                return $mapped;
            }
        }

        return null;
    }

    /**
     * Check if product is ready to sync
     *
     * @param int    $product_id        Product ID.
     * @param string $category_override Category override value.
     * @return array|null Issue array ['type' => string, 'message' => string] or null if ready.
     */
    private static function check_sync_readiness($product_id, $category_override) {
        // Check for category
        if (!$category_override) {
            $product       = wc_get_product($product_id);
            $wc_categories = $product ? $product->get_category_ids() : [];
            $has_category  = false;

            foreach ($wc_categories as $cat_id) {
                if (Zicer_Category_Map::get_zicer_category($cat_id)) {
                    $has_category = true;
                    break;
                }
            }

            if (!$has_category) {
                return [
                    'type'    => 'no_category',
                    'message' => __('No mapped ZICER category', 'zicer-woo-sync'),
                ];
            }
        }

        // Check for region/city
        $region = get_option('zicer_default_region');
        $city   = get_option('zicer_default_city');

        if (!$region || !$city) {
            return [
                'type'    => 'no_location',
                'message' => __('Region and city not configured', 'zicer-woo-sync'),
            ];
        }

        return null;
    }
}
