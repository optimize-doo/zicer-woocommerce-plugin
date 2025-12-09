<?php
/**
 * ZICER Category Map
 *
 * Handles WooCommerce to ZICER category mapping.
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Zicer_Category_Map
 */
class Zicer_Category_Map {

    /**
     * Get the category mapping
     *
     * @return array
     */
    public static function get_mapping() {
        return get_option('zicer_category_mapping', []);
    }

    /**
     * Save category mapping
     *
     * @param array $mapping The mapping array.
     */
    public static function save_mapping($mapping) {
        update_option('zicer_category_mapping', $mapping);
    }

    /**
     * Get ZICER category for a WooCommerce category
     *
     * @param int $wc_category_id WooCommerce category ID.
     * @return string|null ZICER category ID or null.
     */
    public static function get_zicer_category($wc_category_id) {
        $mapping = self::get_mapping();

        // Direct mapping
        if (isset($mapping[$wc_category_id])) {
            return $mapping[$wc_category_id];
        }

        // Try parent category
        $term = get_term($wc_category_id, 'product_cat');
        if ($term && !is_wp_error($term) && $term->parent) {
            return self::get_zicer_category($term->parent);
        }

        // Fallback
        return get_option('zicer_fallback_category', null);
    }

    /**
     * Suggest ZICER category for a product title
     *
     * @param string $product_title Product title.
     * @return array Suggestions array.
     */
    public static function suggest_category($product_title) {
        $api    = Zicer_API_Client::instance();
        $result = $api->get_category_suggestions($product_title);

        if (is_wp_error($result)) {
            return [];
        }

        return $result['suggestions'] ?? [];
    }

    /**
     * Get cached ZICER categories
     *
     * @return array|null Categories or null if cache expired.
     */
    public static function get_cached_categories() {
        $cache_time = get_option('zicer_categories_cache_time', 0);

        // Cache for 24 hours
        if (time() - $cache_time > 86400) {
            return null;
        }

        return get_option('zicer_categories_cache', []);
    }

    /**
     * Flatten categories into a list with paths
     *
     * @param array  $categories  Categories array.
     * @param string $parent_path Parent category path.
     * @return array Flattened categories.
     */
    public static function flatten_categories($categories, $parent_path = '') {
        $flat = [];

        foreach ($categories as $cat) {
            $path   = $parent_path ? "$parent_path > {$cat['title']}" : $cat['title'];
            $flat[] = [
                'id'           => $cat['id'],
                'title'        => $cat['title'],
                'path'         => $path,
                'has_children' => !empty($cat['categories']),
            ];

            if (!empty($cat['categories'])) {
                $flat = array_merge($flat, self::flatten_categories($cat['categories'], $path));
            }
        }

        return $flat;
    }
}
