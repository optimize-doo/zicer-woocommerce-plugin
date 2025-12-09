<?php
/**
 * Sync status view
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats       = Zicer_Queue::get_stats();
$rate_limit  = Zicer_API_Client::instance()->get_rate_limit_status();
$connection  = get_option('zicer_connection_status', []);

// Use stored rate limit from connection if available (more accurate than default)
if (isset($connection['rate_limit']) && $rate_limit['limit'] === 60) {
    $rate_limit['limit'] = $connection['rate_limit'];
}
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
                /* translators: %1$d: remaining requests, %2$d: total limit, %3$d: seconds until reset */
                esc_html__('Remaining requests: %1$d / %2$d | Resets in: %3$d seconds', 'zicer-woo-sync'),
                $rate_limit['remaining'],
                $rate_limit['limit'],
                $rate_limit['reset_in']
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
