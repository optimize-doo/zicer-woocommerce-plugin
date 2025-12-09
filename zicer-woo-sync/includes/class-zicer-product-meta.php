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
        $listing_id        = get_post_meta($post->ID, '_zicer_listing_id', true);
        $last_sync         = get_post_meta($post->ID, '_zicer_last_sync', true);
        $sync_error        = get_post_meta($post->ID, '_zicer_sync_error', true);
        $excluded          = get_post_meta($post->ID, '_zicer_exclude', true);
        $category_override = get_post_meta($post->ID, '_zicer_category', true);

        // Get ZICER categories for dropdown
        $zicer_categories = Zicer_Category_Map::get_cached_categories();
        $flat_categories  = $zicer_categories ? Zicer_Category_Map::flatten_categories($zicer_categories) : [];

        wp_nonce_field('zicer_product_meta', 'zicer_meta_nonce');
        ?>
        <div class="zicer-product-meta">
            <?php if ($excluded === 'yes') : ?>
                <p class="zicer-status excluded">
                    <?php esc_html_e('Excluded from sync', 'zicer-woo-sync'); ?>
                </p>
            <?php elseif ($listing_id) : ?>
                <p class="zicer-status synced">
                    &#10003; <?php esc_html_e('Synced', 'zicer-woo-sync'); ?>
                </p>
                <p class="zicer-info">
                    <strong>ID:</strong> <?php echo esc_html(substr($listing_id, 0, 8)); ?>...
                    <br>
                    <strong><?php esc_html_e('Last sync:', 'zicer-woo-sync'); ?></strong>
                    <?php echo esc_html($last_sync); ?>
                </p>
                <p>
                    <a href="https://zicer.ba/oglas/<?php echo esc_attr($listing_id); ?>"
                       target="_blank" class="button">
                        <?php esc_html_e('View on ZICER', 'zicer-woo-sync'); ?>
                    </a>
                </p>
            <?php elseif ($sync_error) : ?>
                <p class="zicer-status error">
                    &#10007; <?php esc_html_e('Error', 'zicer-woo-sync'); ?>
                </p>
                <p class="zicer-error"><?php echo esc_html($sync_error); ?></p>
            <?php else : ?>
                <p class="zicer-status pending">
                    <?php esc_html_e('Not synced', 'zicer-woo-sync'); ?>
                </p>
            <?php endif; ?>

            <hr>

            <p>
                <button type="button"
                        class="button zicer-sync-now"
                        data-product-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e('Sync Now', 'zicer-woo-sync'); ?>
                </button>
            </p>

            <?php if ($listing_id) : ?>
                <p>
                    <button type="button"
                            class="button zicer-delete-listing"
                            data-product-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Remove from ZICER', 'zicer-woo-sync'); ?>
                    </button>
                </p>
            <?php endif; ?>

            <hr>

            <p>
                <label for="_zicer_category"><strong><?php esc_html_e('Category Override', 'zicer-woo-sync'); ?></strong></label>
            </p>
            <?php if (!empty($flat_categories)) : ?>
                <p>
                    <select name="_zicer_category"
                            id="_zicer_category"
                            class="zicer-select2"
                            style="width: 100%;">
                        <option value=""><?php esc_html_e('-- Use mapped category --', 'zicer-woo-sync'); ?></option>
                        <?php foreach ($flat_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat['id']); ?>"
                                    <?php selected($category_override, $cat['id']); ?>
                                    <?php disabled(!empty($cat['disabled'])); ?>>
                                <?php echo esc_html($cat['path']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="description">
                    <?php esc_html_e('Override the mapped category for this product only.', 'zicer-woo-sync'); ?>
                </p>
            <?php else : ?>
                <p class="description">
                    <?php esc_html_e('Load categories in ZICER settings first.', 'zicer-woo-sync'); ?>
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

        update_post_meta($post_id, '_zicer_condition', $condition);
        update_post_meta($post_id, '_zicer_exclude', $exclude);
        update_post_meta($post_id, '_zicer_category', $category);
    }
}
