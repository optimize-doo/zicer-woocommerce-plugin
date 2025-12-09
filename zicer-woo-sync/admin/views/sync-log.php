<?php
/**
 * Sync log view
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

$page    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$limit   = 50;
$offset  = ($page - 1) * $limit;
$logs    = Zicer_Logger::get_logs($limit, $offset);
$total   = Zicer_Logger::get_count();
$pages   = ceil($total / $limit);
?>

<div class="wrap zicer-log">
    <h1><?php esc_html_e('Log', 'zicer-woo-sync'); ?></h1>

    <form method="post" action="" style="margin-bottom: 20px;">
        <?php wp_nonce_field('zicer_clear_logs', 'zicer_clear_nonce'); ?>
        <button type="submit" name="zicer_clear_logs" class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'zicer-woo-sync'); ?>');">
            <?php esc_html_e('Clear All Logs', 'zicer-woo-sync'); ?>
        </button>
    </form>

    <?php
    // Handle clear logs action
    if (isset($_POST['zicer_clear_logs']) && isset($_POST['zicer_clear_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zicer_clear_nonce'])), 'zicer_clear_logs')) {
        Zicer_Logger::clear_logs();
        echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared.', 'zicer-woo-sync') . '</p></div>';
        $logs = [];
        $total = 0;
    }
    ?>

    <?php if (!empty($logs)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php esc_html_e('Date', 'zicer-woo-sync'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Status', 'zicer-woo-sync'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Product', 'zicer-woo-sync'); ?></th>
                    <th><?php esc_html_e('Message', 'zicer-woo-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td>
                            <span class="zicer-log-status zicer-log-<?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html($log->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log->product_id) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($log->product_id)); ?>">
                                    #<?php echo esc_html($log->product_id); ?>
                                </a>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        printf(
                            /* translators: %d: total number of items */
                            esc_html(_n('%d item', '%d items', $total, 'zicer-woo-sync')),
                            $total
                        );
                        ?>
                    </span>
                    <span class="pagination-links">
                        <?php if ($page > 1) : ?>
                            <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $page - 1)); ?>">
                                &lsaquo;
                            </a>
                        <?php endif; ?>
                        <span class="paging-input">
                            <?php echo esc_html($page); ?> / <?php echo esc_html($pages); ?>
                        </span>
                        <?php if ($page < $pages) : ?>
                            <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $page + 1)); ?>">
                                &rsaquo;
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p><?php esc_html_e('No log entries.', 'zicer-woo-sync'); ?></p>
    <?php endif; ?>

    <p class="zicer-footer"><?php esc_html_e('&copy; 2025 Optimize d.o.o. All rights reserved. Zicer is a registered trademark.', 'zicer-woo-sync'); ?></p>
</div>
