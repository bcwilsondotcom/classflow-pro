<?php
namespace ClassFlowPro\Privacy;

class Exporters
{
    const PAGE_SIZE = 100;

    public static function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
            $exporters['classflow-pro'] = [
                'exporter_friendly_name' => __('ClassFlow Pro', 'classflow-pro'),
                'callback' => [self::class, 'export'],
            ];
            return $exporters;
        });
        add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
            $erasers['classflow-pro'] = [
                'eraser_friendly_name' => __('ClassFlow Pro', 'classflow-pro'),
                'callback' => [self::class, 'erase'],
            ];
            return $erasers;
        });
    }

    public static function export(string $email, int $page = 1)
    {
        $page = max(1, $page);
        $limit = self::PAGE_SIZE;
        $offset = ($page - 1) * $limit;
        $data = [];
        $done = true;
        global $wpdb;

        // Identify user
        $user = get_user_by('email', $email);
        $user_id = $user ? (int)$user->ID : 0;

        // Bookings by user or email
        $bk = $wpdb->prefix . 'cfp_bookings';
        $bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $bk WHERE (customer_email = %s) OR (user_id = %d) ORDER BY created_at ASC LIMIT %d OFFSET %d", $email, $user_id, $limit, $offset), ARRAY_A);
        foreach ($bookings as $b) {
            $done = count($bookings) < $limit;
            $group = __('ClassFlow Pro — Bookings', 'classflow-pro');
            $item = [
                ['name' => 'Booking ID', 'value' => $b['id']],
                ['name' => 'Status', 'value' => $b['status']],
                ['name' => 'Email', 'value' => $b['customer_email']],
                ['name' => 'User ID', 'value' => $b['user_id']],
                ['name' => 'Amount', 'value' => $b['amount_cents'] . ' ' . strtoupper($b['currency'])],
                ['name' => 'Credits Used', 'value' => $b['credits_used']],
                ['name' => 'Coupon', 'value' => $b['coupon_code']],
                ['name' => 'Created', 'value' => $b['created_at']],
            ];
            $data[] = [ 'group_id' => 'classflow-pro-bookings', 'group_label' => $group, 'item_id' => 'booking-' . $b['id'], 'data' => $item ];
        }

        // Packages
        if ($user_id) {
            $pk = $wpdb->prefix . 'cfp_packages';
            $packages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $pk WHERE user_id = %d ORDER BY created_at ASC LIMIT %d OFFSET %d", $user_id, $limit, $offset), ARRAY_A);
            foreach ($packages as $p) {
                $done = $done && (count($packages) < $limit);
                $group = __('ClassFlow Pro — Packages', 'classflow-pro');
                $item = [
                    ['name' => 'Package', 'value' => $p['name']],
                    ['name' => 'Credits', 'value' => $p['credits']],
                    ['name' => 'Remaining', 'value' => $p['credits_remaining']],
                    ['name' => 'Price', 'value' => $p['price_cents'] . ' ' . strtoupper($p['currency'])],
                    ['name' => 'Expires', 'value' => $p['expires_at']],
                ];
                $data[] = [ 'group_id' => 'classflow-pro-packages', 'group_label' => $group, 'item_id' => 'package-' . $p['id'], 'data' => $item ];
            }
        }

        // Intake forms
        if ($user_id) {
            $it = $wpdb->prefix . 'cfp_intake_forms';
            $forms = $wpdb->get_results($wpdb->prepare("SELECT * FROM $it WHERE user_id = %d ORDER BY signed_at ASC LIMIT %d OFFSET %d", $user_id, $limit, $offset), ARRAY_A);
            foreach ($forms as $f) {
                $done = $done && (count($forms) < $limit);
                $payload = json_decode($f['data'], true);
                $flatten = [];
                foreach ((array)$payload as $k => $v) { $flatten[] = ['name' => $k, 'value' => is_scalar($v) ? (string)$v : wp_json_encode($v)]; }
                $flatten[] = ['name' => 'Signed', 'value' => $f['signed_at']];
                $data[] = [ 'group_id' => 'classflow-pro-intake', 'group_label' => __('ClassFlow Pro — Intake', 'classflow-pro'), 'item_id' => 'intake-' . $f['id'], 'data' => $flatten ];
            }
        }

        return [ 'data' => $data, 'done' => $done ];
    }

    public static function erase(string $email, int $page = 1)
    {
        $items_removed = false; $items_retained = false; $messages = [];
        global $wpdb;
        $user = get_user_by('email', $email);
        $user_id = $user ? (int)$user->ID : 0;

        // Anonymize bookings by email
        $bk = $wpdb->prefix . 'cfp_bookings';
        $count = $wpdb->query($wpdb->prepare("UPDATE $bk SET customer_email = NULL, user_id = NULL, metadata = NULL WHERE customer_email = %s OR user_id = %d", $email, $user_id));
        if ($count) $items_removed = true;

        // Delete intake forms (contain sensitive medical info)
        if ($user_id) {
            $it = $wpdb->prefix . 'cfp_intake_forms';
            $count2 = $wpdb->query($wpdb->prepare("DELETE FROM $it WHERE user_id = %d", $user_id));
            if ($count2) $items_removed = true;
        }

        // Anonymize transactions
        $tx = $wpdb->prefix . 'cfp_transactions';
        $count3 = $wpdb->query($wpdb->prepare("UPDATE $tx SET user_id = NULL WHERE user_id = %d", $user_id));
        if ($count3) $items_removed = true;

        // Anonymize private requests
        $pr = $wpdb->prefix . 'cfp_private_requests';
        $count4 = $wpdb->query($wpdb->prepare("UPDATE $pr SET name = NULL, email = NULL, notes = NULL, user_id = NULL WHERE email = %s OR user_id = %d", $email, $user_id));
        if ($count4) $items_removed = true;

        // Customers table
        $cu = $wpdb->prefix . 'cfp_customers';
        $count5 = $wpdb->query($wpdb->prepare("DELETE FROM $cu WHERE email = %s OR user_id = %d", $email, $user_id));
        if ($count5) $items_removed = true;

        return [ 'items_removed' => $items_removed, 'items_retained' => $items_retained, 'messages' => $messages, 'done' => true ];
    }
}

