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
     * @param array $categories Categories array from API.
     * @return array Flattened categories.
     */
    public static function flatten_categories($categories) {
        $flat = [];
        $seen = []; // Track seen IDs to avoid duplicates

        self::flatten_recursive($categories, $flat, $seen);

        return $flat;
    }

    /**
     * Recursively flatten categories
     *
     * @param array $categories Categories array.
     * @param array $flat       Reference to flat array.
     * @param array $seen       Reference to seen IDs array.
     */
    private static function flatten_recursive($categories, &$flat, &$seen) {
        foreach ($categories as $cat) {
            $id = $cat['id'] ?? $cat['uuid'] ?? '';

            // Skip if already processed
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $has_children = !empty($cat['categories']);

            // Determine if this is a leaf category (selectable)
            // Use hierarchyLevels or breadcrumbs to check depth
            $breadcrumbs = $cat['breadcrumbs'] ?? [];
            $level       = count($breadcrumbs) - 1; // 0-indexed level of this category

            // Category is selectable only if it's a leaf (no children)
            // Root categories (level 0) with no children are still selectable
            $is_disabled = $has_children;

            // Build path from hierarchyLevels or refinementValue
            $path = $cat['refinementValue'] ?? $cat['title'];

            $flat[] = [
                'id'           => $id,
                'title'        => $cat['title'],
                'path'         => $path,
                'has_children' => $has_children,
                'disabled'     => $is_disabled,
            ];

            // Recurse into children
            if ($has_children) {
                self::flatten_recursive($cat['categories'], $flat, $seen);
            }
        }
    }
}
