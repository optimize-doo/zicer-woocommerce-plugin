<?php
/**
 * Settings page view
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

$connection   = get_option('zicer_connection_status', []);
$is_connected = !empty($connection['connected']);
$regions      = get_option('zicer_regions_cache', []);
?>
<div class="wrap zicer-settings">
    <h1><?php esc_html_e('Settings', 'zicer-woo-sync'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('zicer_settings'); ?>

        <!-- Connection Section -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Connection', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('API Token', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="password"
                               name="zicer_api_token"
                               id="zicer_api_token"
                               value="<?php echo esc_attr(get_option('zicer_api_token')); ?>"
                               class="regular-text">
                        <button type="button" id="zicer-test-connection" class="button">
                            <?php esc_html_e('Test Connection', 'zicer-woo-sync'); ?>
                        </button>
                        <?php if ($is_connected) : ?>
                            <button type="button" id="zicer-disconnect" class="button">
                                <?php esc_html_e('Disconnect', 'zicer-woo-sync'); ?>
                            </button>
                        <?php endif; ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: link to ZICER profile */
                                esc_html__('You can create an API token in your %s.', 'zicer-woo-sync'),
                                '<a href="https://zicer.ba/moje-integracije" target="_blank">' . esc_html__('ZICER profile settings', 'zicer-woo-sync') . '</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Status', 'zicer-woo-sync'); ?></th>
                    <td>
                        <span id="zicer-connection-status" class="<?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                            <?php if ($is_connected) : ?>
                                &#10003; <?php printf(
                                    /* translators: %s: user email */
                                    esc_html__('Connected as %s', 'zicer-woo-sync'),
                                    esc_html($connection['user'])
                                ); ?>
                                <?php if (!empty($connection['shop'])) : ?>
                                    (<?php echo esc_html($connection['shop']); ?>)
                                <?php elseif (empty($connection['has_shop'])) : ?>
                                    &mdash; <a href="https://zicer.ba/" target="_blank"><?php esc_html_e('Create a shop for higher rate limits', 'zicer-woo-sync'); ?></a>
                                <?php endif; ?>
                                <?php if (!empty($connection['rate_limit'])) : ?>
                                    <span class="zicer-rate-limit">
                                        &mdash;
                                        <?php printf(
                                            /* translators: %d: rate limit per minute */
                                            esc_html__('Rate limit: %d/min', 'zicer-woo-sync'),
                                            $connection['rate_limit']
                                        ); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                &#10007; <?php esc_html_e('Not connected', 'zicer-woo-sync'); ?>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Sync Settings -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Synchronization', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Real-time Sync', 'zicer-woo-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="zicer_realtime_sync"
                                   value="1"
                                   <?php checked(get_option('zicer_realtime_sync', '1'), '1'); ?>>
                            <?php esc_html_e('Automatically sync products when created or updated', 'zicer-woo-sync'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Delete Unavailable', 'zicer-woo-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="zicer_delete_on_unavailable"
                                   value="1"
                                   <?php checked(get_option('zicer_delete_on_unavailable', '1'), '1'); ?>>
                            <?php esc_html_e('Automatically remove from ZICER when product is unavailable', 'zicer-woo-sync'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Stock Threshold', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="number"
                               name="zicer_stock_threshold"
                               value="<?php echo esc_attr(get_option('zicer_stock_threshold', 0)); ?>"
                               min="0"
                               class="small-text">
                        <p class="description">
                            <?php esc_html_e('Product is considered available if stock is greater than this number. 0 = any stock quantity.', 'zicer-woo-sync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Title Settings -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Title', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Truncate Title', 'zicer-woo-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="zicer_truncate_title"
                                   value="1"
                                   <?php checked(get_option('zicer_truncate_title', '0'), '1'); ?>>
                            <?php esc_html_e('Truncate title to maximum length', 'zicer-woo-sync'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Maximum Length', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="number"
                               name="zicer_title_max_length"
                               value="<?php echo esc_attr(get_option('zicer_title_max_length', 65)); ?>"
                               min="10"
                               max="200"
                               class="small-text">
                        <span><?php esc_html_e('characters', 'zicer-woo-sync'); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Description Settings -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Description', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Description Mode', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_description_mode">
                            <option value="product" <?php selected(get_option('zicer_description_mode', 'product'), 'product'); ?>>
                                <?php esc_html_e('Use product description', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="replace" <?php selected(get_option('zicer_description_mode'), 'replace'); ?>>
                                <?php esc_html_e('Replace with template', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="prepend" <?php selected(get_option('zicer_description_mode'), 'prepend'); ?>>
                                <?php esc_html_e('Add template before description', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="append" <?php selected(get_option('zicer_description_mode'), 'append'); ?>>
                                <?php esc_html_e('Add template after description', 'zicer-woo-sync'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Description Template', 'zicer-woo-sync'); ?></th>
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
                            <?php esc_html_e('Available variables: {product_name}, {product_price}, {product_sku}, {shop_name}', 'zicer-woo-sync'); ?>
                            <br>
                            <?php esc_html_e('Supported HTML: strong, em, u, s, mark, h1-h4, p, br, hr, blockquote, ul, ol, li, sub, sup, a', 'zicer-woo-sync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Image Settings -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Images', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Image Sync', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_sync_images">
                            <option value="all" <?php selected(get_option('zicer_sync_images', 'all'), 'all'); ?>>
                                <?php esc_html_e('All images', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="featured" <?php selected(get_option('zicer_sync_images'), 'featured'); ?>>
                                <?php esc_html_e('Featured image only', 'zicer-woo-sync'); ?>
                            </option>
                            <option value="none" <?php selected(get_option('zicer_sync_images'), 'none'); ?>>
                                <?php esc_html_e('No images', 'zicer-woo-sync'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Maximum Images', 'zicer-woo-sync'); ?></th>
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
            <h2><?php esc_html_e('Price', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Currency Conversion', 'zicer-woo-sync'); ?></th>
                    <td>
                        <input type="number"
                               name="zicer_price_conversion"
                               value="<?php echo esc_attr(get_option('zicer_price_conversion', 1)); ?>"
                               step="0.0001"
                               min="0.0001"
                               class="small-text">
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: WooCommerce currency code */
                                esc_html__('Multiplier for conversion to KM. Current WooCommerce currency: %s', 'zicer-woo-sync'),
                                esc_html(get_woocommerce_currency())
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Settings -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Advanced', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Debug Logging', 'zicer-woo-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="zicer_debug_logging"
                                   value="1"
                                   <?php checked(get_option('zicer_debug_logging', '0'), '1'); ?>>
                            <?php esc_html_e('Log all API requests and responses', 'zicer-woo-sync'); ?>
                        </label>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: link to log page */
                                esc_html__('View logs in the %s tab.', 'zicer-woo-sync'),
                                '<a href="' . esc_url(admin_url('admin.php?page=zicer-log')) . '">' . esc_html__('Log', 'zicer-woo-sync') . '</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Default Values -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Default Values', 'zicer-woo-sync'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Default Condition', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_default_condition">
                            <?php foreach (Zicer_Settings::get_conditions() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                        <?php selected(get_option('zicer_default_condition', 'Novo'), $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Default Region', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_default_region" id="zicer_default_region">
                            <?php $current_region = get_option('zicer_default_region', ''); ?>
                            <option value="" <?php selected($current_region, ''); ?>><?php esc_html_e('-- Select --', 'zicer-woo-sync'); ?></option>
                            <?php
                            foreach ($regions as $region) :
                                $region_id = $region['uuid'] ?? $region['id'] ?? '';
                                $disabled  = !empty($region['disabled']);
                            ?>
                                <option value="<?php echo esc_attr($region_id); ?>"
                                        <?php selected($current_region, $region_id); ?>
                                        <?php disabled($disabled); ?>>
                                    <?php echo esc_html($region['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="zicer-refresh-regions" class="button">
                            <?php esc_html_e('Refresh', 'zicer-woo-sync'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Default City', 'zicer-woo-sync'); ?></th>
                    <td>
                        <select name="zicer_default_city" id="zicer_default_city">
                            <option value=""><?php esc_html_e('-- Select region first --', 'zicer-woo-sync'); ?></option>
                        </select>
                        <input type="hidden" id="zicer_current_city" value="<?php echo esc_attr(get_option('zicer_default_city', '')); ?>">
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Save Settings', 'zicer-woo-sync')); ?>
    </form>

    <p class="zicer-footer"><?php esc_html_e('&copy; 2025 Optimize d.o.o. All rights reserved. Zicer is a registered trademark.', 'zicer-woo-sync'); ?></p>
</div>
