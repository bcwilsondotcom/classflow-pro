<?php
namespace ClassFlowPro\Admin;

use ClassFlowPro\Accounting\QuickBooks;

class QuickBooksTools
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>QuickBooks Tools</h1>';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['cfp_qb_sync_items']) && check_admin_referer('cfp_qb_tools')) {
                $count = 0;
                $classes = get_posts(['post_type' => 'cfp_class', 'numberposts' => -1, 'post_status' => 'publish']);
                foreach ($classes as $c) {
                    try { if (QuickBooks::ensure_item_for_class((int)$c->ID)) $count++; } catch (\Throwable $e) {}
                }
                echo '<div class="notice notice-success"><p>Ensured items for ' . intval($count) . ' classes.</p></div>';
            }
            if (isset($_POST['cfp_qb_resync_booking']) && check_admin_referer('cfp_qb_tools')) {
                $bid = intval($_POST['booking_id'] ?? 0);
                if ($bid) { try { QuickBooks::create_sales_receipt_for_booking($bid); echo '<div class="notice notice-success"><p>Re-synced booking #' . $bid . '.</p></div>'; } catch (\Throwable $e) { echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>'; } }
            }
            if (isset($_POST['cfp_qb_resync_recent']) && check_admin_referer('cfp_qb_tools')) {
                $days = max(1, min(90, intval($_POST['days'] ?? 7)));
                global $wpdb;
                $bk = $wpdb->prefix . 'cfp_bookings';
                $rows = $wpdb->get_results($wpdb->prepare("SELECT id FROM $bk WHERE status='confirmed' AND amount_cents>0 AND updated_at >= %s ORDER BY updated_at DESC LIMIT 200", gmdate('Y-m-d H:i:s', strtotime('-'.$days.' days'))), ARRAY_A);
                $done = 0;
                foreach ($rows as $r) { try { QuickBooks::create_sales_receipt_for_booking((int)$r['id']); $done++; } catch (\Throwable $e) {} }
                echo '<div class="notice notice-success"><p>Re-synced ' . intval($done) . ' bookings from last ' . intval($days) . ' day(s).</p></div>';
            }
        }

        echo '<h2>Item Mappings</h2>';
        echo '<form method="post">';
        wp_nonce_field('cfp_qb_tools');
        echo '<p><button class="button">Ensure Items for All Classes</button><input type="hidden" name="cfp_qb_sync_items" value="1"></p>';
        echo '</form>';

        echo '<h2>Re-sync Sales Receipts</h2>';
        echo '<form method="post" style="margin-bottom:12px;">';
        wp_nonce_field('cfp_qb_tools');
        echo '<p><label>Booking ID <input type="number" name="booking_id" min="1" class="small-text"></label> <button class="button">Re-sync Booking</button><input type="hidden" name="cfp_qb_resync_booking" value="1"></p>';
        echo '</form>';
        echo '<form method="post">';
        wp_nonce_field('cfp_qb_tools');
        echo '<p><label>Days back <input type="number" name="days" min="1" max="90" value="7" class="small-text"></label> <button class="button">Re-sync Recent</button><input type="hidden" name="cfp_qb_resync_recent" value="1"></p>';
        echo '</form>';

        echo '</div>';
    }
}

