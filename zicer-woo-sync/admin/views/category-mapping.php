<?php
/**
 * Category mapping view
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

$wc_categories = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
    'orderby'    => 'name',
]);

$zicer_categories = Zicer_Category_Map::get_cached_categories();
$flat_categories  = $zicer_categories ? Zicer_Category_Map::flatten_categories($zicer_categories) : [];
$mapping          = Zicer_Category_Map::get_mapping();
$saved            = isset($_GET['saved']) && $_GET['saved'] === '1';
?>

<div class="wrap zicer-categories">
    <h1><?php esc_html_e('Category Mapping', 'zicer-woo-sync'); ?></h1>

    <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Mapping saved successfully.', 'zicer-woo-sync'); ?></p>
        </div>
    <?php endif; ?>

    <p class="description">
        <?php esc_html_e('Map WooCommerce categories to ZICER categories. Products without mapping will use the fallback category.', 'zicer-woo-sync'); ?>
    </p>

    <?php if (empty($zicer_categories)) : ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('ZICER categories are not loaded.', 'zicer-woo-sync'); ?>
                <button type="button" id="zicer-load-categories" class="button">
                    <?php esc_html_e('Load Categories', 'zicer-woo-sync'); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="zicer_save_category_mapping">
        <?php wp_nonce_field('zicer_category_mapping', 'zicer_nonce'); ?>

        <!-- Fallback Category -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Fallback Category', 'zicer-woo-sync'); ?></h2>
            <p class="description">
                <?php esc_html_e('This category will be used when a product has no mapped category.', 'zicer-woo-sync'); ?>
            </p>
            <select name="fallback_category" class="zicer-category-select">
                <option value=""><?php esc_html_e('-- None --', 'zicer-woo-sync'); ?></option>
                <?php
                $fallback = get_option('zicer_fallback_category', '');
                foreach ($flat_categories as $zcat) :
                    if (!$zcat['has_children']) : // Only leaf categories
                ?>
                    <option value="<?php echo esc_attr($zcat['id']); ?>"
                            <?php selected($fallback, $zcat['id']); ?>>
                        <?php echo esc_html($zcat['path']); ?>
                    </option>
                <?php
                    endif;
                endforeach;
                ?>
            </select>
        </div>

        <!-- Category Mapping Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('WooCommerce Category', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('ZICER Category', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('Auto-suggest', 'zicer-woo-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($wc_categories) && !is_wp_error($wc_categories)) : ?>
                    <?php foreach ($wc_categories as $wc_cat) : ?>
                        <tr>
                            <td>
                                <?php
                                $depth  = 0;
                                $parent = $wc_cat->parent;
                                while ($parent) {
                                    $depth++;
                                    $parent_term = get_term($parent, 'product_cat');
                                    $parent      = $parent_term && !is_wp_error($parent_term) ? $parent_term->parent : 0;
                                }
                                echo esc_html(str_repeat('— ', $depth) . $wc_cat->name);
                                ?>
                                <span class="count">(<?php echo esc_html($wc_cat->count); ?>)</span>
                            </td>
                            <td>
                                <select name="mapping[<?php echo esc_attr($wc_cat->term_id); ?>]"
                                        class="zicer-category-select"
                                        data-wc-cat="<?php echo esc_attr($wc_cat->name); ?>">
                                    <option value=""><?php esc_html_e('-- Do not map --', 'zicer-woo-sync'); ?></option>
                                    <?php foreach ($flat_categories as $zcat) : ?>
                                        <?php if (!$zcat['has_children']) : // Only leaf categories ?>
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
                                        data-term-id="<?php echo esc_attr($wc_cat->term_id); ?>"
                                        data-term-name="<?php echo esc_attr($wc_cat->name); ?>">
                                    <?php esc_html_e('Suggest', 'zicer-woo-sync'); ?>
                                </button>
                                <span class="spinner"></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3"><?php esc_html_e('No WooCommerce categories found.', 'zicer-woo-sync'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php submit_button(__('Save Mapping', 'zicer-woo-sync')); ?>
    </form>
</div>
