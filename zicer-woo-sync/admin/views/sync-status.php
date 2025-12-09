<?php
/**
 * Sync status view
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats      = Zicer_Queue::get_stats();
$connection = get_option('zicer_connection_status', []);
$rate_limit = $connection['rate_limit'] ?? 60;
?>

<div class="wrap zicer-status">
    <h1><?php esc_html_e('Sync Status', 'zicer-woo-sync'); ?></h1>

    <div class="zicer-stats-grid">
        <div class="zicer-stat-card">
            <span class="stat-number"><?php echo esc_html($stats['pending']); ?></span>
            <span class="stat-label"><?php esc_html_e('Pending', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card">
            <span class="stat-number"><?php echo esc_html($stats['processing']); ?></span>
            <span class="stat-label"><?php esc_html_e('Processing', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card success">
            <span class="stat-number"><?php echo esc_html($stats['completed']); ?></span>
            <span class="stat-label"><?php esc_html_e('Completed', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card error">
            <span class="stat-number"><?php echo esc_html($stats['failed']); ?></span>
            <span class="stat-label"><?php esc_html_e('Failed', 'zicer-woo-sync'); ?></span>
        </div>
    </div>

    <div class="zicer-card">
        <h2><?php esc_html_e('API Rate Limit', 'zicer-woo-sync'); ?></h2>
        <p>
            <?php
            printf(
                /* translators: %d: rate limit per minute */
                esc_html__('%d requests per minute', 'zicer-woo-sync'),
                $rate_limit
            );
            ?>
        </p>
    </div>

    <div class="zicer-card">
        <h2><?php esc_html_e('Actions', 'zicer-woo-sync'); ?></h2>

        <p>
            <button type="button" id="zicer-bulk-sync" class="button button-primary">
                <?php esc_html_e('Start Bulk Sync', 'zicer-woo-sync'); ?>
            </button>
            <span class="description">
                <?php esc_html_e('Adds all products to the sync queue', 'zicer-woo-sync'); ?>
            </span>
        </p>

        <?php if ($stats['pending'] > 0 || $stats['processing'] > 0) : ?>
            <p>
                <button type="button" id="zicer-process-queue" class="button">
                    <?php esc_html_e('Process Queue Now', 'zicer-woo-sync'); ?>
                </button>
                <span class="description">
                    <?php esc_html_e('Manually trigger queue processing', 'zicer-woo-sync'); ?>
                </span>
            </p>
        <?php endif; ?>

        <?php if ($stats['failed'] > 0) : ?>
            <p>
                <button type="button" id="zicer-retry-failed" class="button">
                    <?php esc_html_e('Retry Failed', 'zicer-woo-sync'); ?>
                </button>
                <button type="button" id="zicer-clear-failed" class="button">
                    <?php esc_html_e('Clear Failed', 'zicer-woo-sync'); ?>
                </button>
            </p>
        <?php endif; ?>
    </div>

    <?php
    $failed_items = Zicer_Queue::get_failed_items();
    if (!empty($failed_items)) :
    ?>
    <div class="zicer-card">
        <h2><?php esc_html_e('Failed Items', 'zicer-woo-sync'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('Action', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('Error', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('Time', 'zicer-woo-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($failed_items as $item) : ?>
                    <tr>
                        <td>
                            <?php if ($item->product_name) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($item->product_id)); ?>">
                                    <?php echo esc_html($item->product_name); ?>
                                </a>
                            <?php else : ?>
                                #<?php echo esc_html($item->product_id); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($item->action); ?></td>
                        <td class="zicer-error"><?php echo esc_html($item->error_message); ?></td>
                        <td><?php echo esc_html($item->processed_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="zicer-card">
        <h2><?php esc_html_e('Synced Products', 'zicer-woo-sync'); ?></h2>
        <?php
        $synced_products = new WP_Query([
            'post_type'      => 'product',
            'posts_per_page' => 20,
            'meta_query'     => [
                ['key' => '_zicer_listing_id', 'compare' => 'EXISTS'],
            ],
        ]);

        if ($synced_products->have_posts()) :
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Product', 'zicer-woo-sync'); ?></th>
                        <th><?php esc_html_e('ZICER ID', 'zicer-woo-sync'); ?></th>
                        <th><?php esc_html_e('Last Sync', 'zicer-woo-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($synced_products->have_posts()) : $synced_products->the_post(); ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link()); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </td>
                            <td>
                                <?php
                                $lid = get_post_meta(get_the_ID(), '_zicer_listing_id', true);
                                echo esc_html(substr($lid, 0, 8)) . '...';
                                ?>
                            </td>
                            <td><?php echo esc_html(get_post_meta(get_the_ID(), '_zicer_last_sync', true)); ?></td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No synced products.', 'zicer-woo-sync'); ?></p>
        <?php endif; ?>
    </div>
</div>
