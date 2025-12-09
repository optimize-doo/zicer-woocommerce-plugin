<?php
/**
 * Sync queue view
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats      = Zicer_Queue::get_stats();
$connection = get_option('zicer_connection_status', []);
$rate_limit = $connection['rate_limit'] ?? 60;
$total_queue = $stats['pending'] + $stats['processing'];
$pending_items = Zicer_Queue::get_pending_items(20);
$failed_items = Zicer_Queue::get_failed_items(20);
?>

<div class="wrap zicer-status">
    <h1><?php esc_html_e('Queue', 'zicer-woo-sync'); ?></h1>

    <!-- Stats Grid -->
    <div class="zicer-stats-grid">
        <div class="zicer-stat-card<?php echo $stats['pending'] > 0 ? ' pending' : ''; ?>">
            <span class="stat-number" id="stat-pending"><?php echo esc_html($stats['pending']); ?></span>
            <span class="stat-label"><?php esc_html_e('Pending', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card<?php echo $stats['processing'] > 0 ? ' pending' : ''; ?>">
            <span class="stat-number" id="stat-processing"><?php echo esc_html($stats['processing']); ?></span>
            <span class="stat-label"><?php esc_html_e('Processing', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card<?php echo $stats['completed'] > 0 ? ' success' : ''; ?>">
            <span class="stat-number" id="stat-completed"><?php echo esc_html($stats['completed']); ?></span>
            <span class="stat-label"><?php esc_html_e('Completed', 'zicer-woo-sync'); ?></span>
        </div>
        <div class="zicer-stat-card<?php echo $stats['failed'] > 0 ? ' error' : ''; ?>">
            <span class="stat-number" id="stat-failed"><?php echo esc_html($stats['failed']); ?></span>
            <span class="stat-label"><?php esc_html_e('Failed', 'zicer-woo-sync'); ?></span>
        </div>
    </div>

    <!-- Progress Bar (shown when queue has items) -->
    <?php if ($total_queue > 0) : ?>
    <div class="zicer-card" id="zicer-progress-card">
        <h2><?php esc_html_e('Processing', 'zicer-woo-sync'); ?></h2>
        <div class="zicer-progress-wrapper">
            <div class="zicer-progress-bar">
                <div class="zicer-progress-fill" id="zicer-progress-fill" style="width: 0%"></div>
            </div>
            <span class="zicer-progress-text" id="zicer-progress-text">
                <?php printf(esc_html__('%d items in queue', 'zicer-woo-sync'), $total_queue); ?>
            </span>
        </div>
        <p>
            <button type="button" id="zicer-process-queue" class="button button-primary">
                <?php esc_html_e('Process Now', 'zicer-woo-sync'); ?>
            </button>
            <span class="spinner" id="zicer-process-spinner"></span>
        </p>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="zicer-card">
        <h2><?php esc_html_e('Actions', 'zicer-woo-sync'); ?></h2>

        <p>
            <button type="button" id="zicer-bulk-sync" class="button button-primary">
                <?php esc_html_e('Sync All Products', 'zicer-woo-sync'); ?>
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

        <p class="description">
            <?php
            printf(
                /* translators: %d: rate limit per minute */
                esc_html__('API rate limit: %d requests per minute', 'zicer-woo-sync'),
                $rate_limit
            );
            ?>
        </p>
    </div>

    <!-- Pending Items -->
    <?php if (!empty($pending_items)) : ?>
    <div class="zicer-card">
        <h2><?php esc_html_e('Pending Items', 'zicer-woo-sync'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('Action', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('Added', 'zicer-woo-sync'); ?></th>
                </tr>
            </thead>
            <tbody id="pending-items-body">
                <?php foreach ($pending_items as $item) : ?>
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
                        <td>
                            <span class="zicer-action-badge zicer-action-<?php echo esc_attr($item->action); ?>">
                                <?php echo esc_html($item->action); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($item->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($stats['pending'] > 20) : ?>
            <p class="description">
                <?php printf(esc_html__('Showing 20 of %d pending items', 'zicer-woo-sync'), $stats['pending']); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Failed Items -->
    <?php if (!empty($failed_items)) : ?>
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
                        <td>
                            <span class="zicer-action-badge zicer-action-<?php echo esc_attr($item->action); ?>">
                                <?php echo esc_html($item->action); ?>
                            </span>
                        </td>
                        <td class="zicer-error"><?php echo esc_html($item->error_message); ?></td>
                        <td><?php echo esc_html($item->processed_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
