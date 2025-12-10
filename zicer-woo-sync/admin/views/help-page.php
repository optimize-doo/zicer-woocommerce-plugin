<?php
/**
 * Help page view
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin_data = get_plugin_data(ZICER_WOO_PLUGIN_DIR . 'zicer-woo-sync.php');
$version = $plugin_data['Version'] ?? '1.0.0';
?>
<div class="wrap zicer-help">
    <h1><?php esc_html_e('Help', 'zicer-woo-sync'); ?></h1>

    <div class="zicer-help-container">
        <!-- Quick Start -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Quick Start', 'zicer-woo-sync'); ?></h2>
            <ol>
                <li>
                    <strong><?php esc_html_e('Get API Token', 'zicer-woo-sync'); ?></strong><br>
                    <?php
                    printf(
                        /* translators: %s: link to ZICER */
                        esc_html__('Log in to %s, go to profile settings, and generate an API token.', 'zicer-woo-sync'),
                        '<a href="https://zicer.ba" target="_blank">zicer.ba</a>'
                    );
                    ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Connect', 'zicer-woo-sync'); ?></strong><br>
                    <?php
                    printf(
                        /* translators: %s: link to settings page */
                        esc_html__('Go to %s, enter your token, and click Connect.', 'zicer-woo-sync'),
                        '<a href="' . esc_url(admin_url('admin.php?page=zicer-sync')) . '">' . esc_html__('Settings', 'zicer-woo-sync') . '</a>'
                    );
                    ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Map Categories', 'zicer-woo-sync'); ?></strong><br>
                    <?php
                    printf(
                        /* translators: %s: link to categories page */
                        esc_html__('Go to %s and map your WooCommerce categories to ZICER categories.', 'zicer-woo-sync'),
                        '<a href="' . esc_url(admin_url('admin.php?page=zicer-categories')) . '">' . esc_html__('Categories', 'zicer-woo-sync') . '</a>'
                    );
                    ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Sync Products', 'zicer-woo-sync'); ?></strong><br>
                    <?php esc_html_e('Enable products for sync from the product edit page or use bulk actions.', 'zicer-woo-sync'); ?>
                </li>
            </ol>
        </div>

        <!-- Settings Explained -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Settings Explained', 'zicer-woo-sync'); ?></h2>

            <h3><?php esc_html_e('Connection', 'zicer-woo-sync'); ?></h3>
            <dl>
                <dt><?php esc_html_e('API Token', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Your ZICER API token starting with "zic_". Required to sync products.', 'zicer-woo-sync'); ?></dd>
            </dl>

            <h3><?php esc_html_e('Sync Options', 'zicer-woo-sync'); ?></h3>
            <dl>
                <dt><?php esc_html_e('Real-time Sync', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('When enabled, products are automatically synced when you save them in WooCommerce.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Delete on Unavailable', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Automatically remove listings from ZICER when products go out of stock or are unpublished.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Stock Threshold', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Minimum stock quantity for a product to be considered available on ZICER.', 'zicer-woo-sync'); ?></dd>
            </dl>

            <h3><?php esc_html_e('Product Data', 'zicer-woo-sync'); ?></h3>
            <dl>
                <dt><?php esc_html_e('Default Condition', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Product condition used when not specified per-product (New, Used, Open box, etc.).', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Price Conversion', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Multiplier to convert your prices to KM (Convertible Mark). Set to 1 if already in KM.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Title Truncation', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('ZICER has a title length limit. Enable to automatically truncate long titles.', 'zicer-woo-sync'); ?></dd>
            </dl>

            <h3><?php esc_html_e('Description', 'zicer-woo-sync'); ?></h3>
            <dl>
                <dt><?php esc_html_e('Description Mode', 'zicer-woo-sync'); ?></dt>
                <dd>
                    <ul>
                        <li><strong><?php esc_html_e('Product description', 'zicer-woo-sync'); ?></strong> - <?php esc_html_e('Use WooCommerce product description as-is.', 'zicer-woo-sync'); ?></li>
                        <li><strong><?php esc_html_e('Replace', 'zicer-woo-sync'); ?></strong> - <?php esc_html_e('Use template instead of product description.', 'zicer-woo-sync'); ?></li>
                        <li><strong><?php esc_html_e('Prepend', 'zicer-woo-sync'); ?></strong> - <?php esc_html_e('Add template before product description.', 'zicer-woo-sync'); ?></li>
                        <li><strong><?php esc_html_e('Append', 'zicer-woo-sync'); ?></strong> - <?php esc_html_e('Add template after product description.', 'zicer-woo-sync'); ?></li>
                    </ul>
                </dd>

                <dt><?php esc_html_e('Template Variables', 'zicer-woo-sync'); ?></dt>
                <dd>
                    <code>{product_name}</code>, <code>{product_price}</code>, <code>{product_sku}</code>, <code>{shop_name}</code>
                </dd>
            </dl>

            <h3><?php esc_html_e('Images', 'zicer-woo-sync'); ?></h3>
            <dl>
                <dt><?php esc_html_e('Sync Images', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Upload product images to ZICER. Disable to create listings without images.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Max Images', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Maximum number of images to sync per product. ZICER may have limits.', 'zicer-woo-sync'); ?></dd>
            </dl>
        </div>

        <!-- Category Mapping -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Category Mapping', 'zicer-woo-sync'); ?></h2>
            <p><?php esc_html_e('Each WooCommerce category must be mapped to a ZICER category before products can sync.', 'zicer-woo-sync'); ?></p>
            <ul>
                <li><?php esc_html_e('Products without a mapped category will fail to sync.', 'zicer-woo-sync'); ?></li>
                <li><?php esc_html_e('Use "Suggest" button for automatic category suggestions.', 'zicer-woo-sync'); ?></li>
                <li><?php esc_html_e('Child categories inherit parent mapping if not explicitly set.', 'zicer-woo-sync'); ?></li>
            </ul>
        </div>

        <!-- Sync Queue -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Sync Queue', 'zicer-woo-sync'); ?></h2>
            <p><?php esc_html_e('The queue shows products waiting to be synced with ZICER.', 'zicer-woo-sync'); ?></p>
            <dl>
                <dt><?php esc_html_e('Pending', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Products waiting in queue to be processed.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Processing', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Currently being synced with ZICER.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Failed', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Sync failed. Check the error message and retry or fix the issue.', 'zicer-woo-sync'); ?></dd>
            </dl>
            <p><?php esc_html_e('The queue processes automatically via WordPress cron, or you can process manually.', 'zicer-woo-sync'); ?></p>
        </div>

        <!-- Per-Product Settings -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Per-Product Settings', 'zicer-woo-sync'); ?></h2>
            <p><?php esc_html_e('Each product has a "ZICER Sync" panel in the sidebar:', 'zicer-woo-sync'); ?></p>
            <dl>
                <dt><?php esc_html_e('ZICER Category', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Override the mapped category for this specific product.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Exclude from sync', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Prevent this product from syncing to ZICER.', 'zicer-woo-sync'); ?></dd>
            </dl>
            <p><?php esc_html_e('In Product Data → General tab:', 'zicer-woo-sync'); ?></p>
            <dl>
                <dt><?php esc_html_e('ZICER Condition', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Override the default condition for this product.', 'zicer-woo-sync'); ?></dd>
            </dl>
        </div>

        <!-- Troubleshooting -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Troubleshooting', 'zicer-woo-sync'); ?></h2>

            <h3><?php esc_html_e('Common Issues', 'zicer-woo-sync'); ?></h3>
            <dl>
                <dt><?php esc_html_e('Connection failed', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Check that your API token is correct and not expired. Generate a new one from zicer.ba if needed.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Product not syncing', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Ensure the product category is mapped to a ZICER category and the product is not excluded.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Rate limit exceeded', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('ZICER has request limits that vary by account tier. The plugin handles this automatically, but bulk syncs may take time.', 'zicer-woo-sync'); ?></dd>

                <dt><?php esc_html_e('Images not uploading', 'zicer-woo-sync'); ?></dt>
                <dd><?php esc_html_e('Check that "Sync Images" is enabled and images are accessible. Large images may timeout.', 'zicer-woo-sync'); ?></dd>
            </dl>
        </div>

        <!-- Support -->
        <div class="zicer-card">
            <h2><?php esc_html_e('Support', 'zicer-woo-sync'); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: %s: link to GitHub issues */
                    esc_html__('For bug reports and feature requests, visit %s.', 'zicer-woo-sync'),
                    '<a href="https://github.com/optimize-doo/zicer-woocommerce-plugin/issues" target="_blank">GitHub Issues</a>'
                );
                ?>
            </p>
            <p class="zicer-version">
                <?php
                printf(
                    /* translators: %s: version number */
                    esc_html__('Version: %s', 'zicer-woo-sync'),
                    esc_html($version)
                );
                ?>
            </p>
        </div>
    </div>
</div>

<style>
.zicer-help-container {
    max-width: 800px;
}
.zicer-help .zicer-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}
.zicer-help .zicer-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.zicer-help .zicer-card h3 {
    margin-top: 20px;
    margin-bottom: 10px;
    color: #1d2327;
}
.zicer-help dl {
    margin: 0;
}
.zicer-help dt {
    font-weight: 600;
    margin-top: 15px;
}
.zicer-help dd {
    margin-left: 0;
    margin-top: 5px;
    color: #50575e;
}
.zicer-help dd ul {
    margin: 5px 0 0 20px;
}
.zicer-help ol {
    margin-left: 20px;
}
.zicer-help ol li {
    margin-bottom: 15px;
}
.zicer-help code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
}
.zicer-help .zicer-version {
    margin-top: 15px;
    color: #888;
    font-size: 12px;
}
</style>
