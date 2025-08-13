<?php
namespace ClassFlowPro\Memberships;

use WP_Error;

class Manager
{
    public static function list_plans(): array
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_membership_plans';
        $rows = $wpdb->get_results("SELECT * FROM $t WHERE active=1 ORDER BY id DESC", ARRAY_A) ?: [];
        return array_map(function($r){ $r['id']=(int)$r['id']; $r['credits_per_period']=(int)$r['credits_per_period']; $r['active']=(int)$r['active']; return $r; }, $rows);
    }

    public static function get_plan(int $id): ?array
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_membership_plans';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function ensure_membership(int $user_id, int $plan_id): int
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_memberships';
        $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND plan_id=%d ORDER BY id DESC LIMIT 1", $user_id, $plan_id));
        if ($id) return $id;
        $wpdb->insert($t, [
            'user_id'=>$user_id,
            'plan_id'=>$plan_id,
            'status'=>'active',
        ], ['%d','%d','%s']);
        return (int)$wpdb->insert_id;
    }

    public static function start_checkout_session(int $user_id, int $plan_id)
    {
        $plan = self::get_plan($plan_id);
        if (!$plan || empty($plan['stripe_price_id'])) {
            return new WP_Error('cfp_plan_invalid', __('Invalid plan', 'classflow-pro'));
        }
        // Use Stripe Checkout for subscriptions
        if (!class_exists('ClassFlowPro\\Payments\\StripeGateway')) {
            return new WP_Error('cfp_stripe_missing', __('Stripe not configured', 'classflow-pro'));
        }
        $user = get_user_by('id', $user_id);
        $success = add_query_arg(['cfp_checkout'=>'success','type'=>'membership','plan_id'=>$plan_id], home_url('/'));
        $cancel = add_query_arg(['cfp_checkout'=>'cancel','type'=>'membership','plan_id'=>$plan_id], home_url('/'));
        return \ClassFlowPro\Payments\StripeGateway::create_checkout_session_subscription([
            'price_id' => $plan['stripe_price_id'],
            'customer_email' => $user ? $user->user_email : null,
            'success_url' => $success,
            'cancel_url' => $cancel,
            'metadata' => [ 'plan_id' => (string)$plan_id, 'user_id' => (string)$user_id ],
        ]);
    }

    public static function update_membership_from_stripe(string $subscription_id, array $sub): void
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_memberships';
        $status = (string)($sub['status'] ?? 'active');
        $period_start = isset($sub['current_period_start']) ? (int)$sub['current_period_start'] : null;
        $period_end = isset($sub['current_period_end']) ? (int)$sub['current_period_end'] : null;
        $user_id = 0; $plan_id = 0;
        $metadata = $sub['metadata'] ?? [];
        if (!empty($metadata['user_id'])) { $user_id = (int)$metadata['user_id']; }
        if (!empty($metadata['plan_id'])) { $plan_id = (int)$metadata['plan_id']; }

        // Try locate membership by stripe_subscription_id or fallback by user+plan
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE stripe_subscription_id=%s", $subscription_id), ARRAY_A);
        if (!$row && $user_id && $plan_id) {
            $mid = self::ensure_membership($user_id, $plan_id);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $mid), ARRAY_A);
        }
        if ($row) {
            $wpdb->update($t, [
                'status' => $status,
                'stripe_subscription_id' => $subscription_id,
                'current_period_start' => $period_start ? gmdate('Y-m-d H:i:s', $period_start) : null,
                'current_period_end' => $period_end ? gmdate('Y-m-d H:i:s', $period_end) : null,
            ], [ 'id' => (int)$row['id'] ], ['%s','%s','%s','%s'], ['%d']);
        }
    }

    public static function grant_periodic_credits_from_invoice(array $invoice): void
    {
        // Called on invoice.paid for subscriptions; grant credits to the user.
        try {
            $lines = $invoice['lines']['data'] ?? [];
            foreach ($lines as $line) {
                $sub_id = (string)($line['subscription'] ?? '');
                if (!$sub_id) continue;
                $meta = $line['metadata'] ?? [];
                $user_id = isset($meta['user_id']) ? (int)$meta['user_id'] : 0;
                $plan_id = isset($meta['plan_id']) ? (int)$meta['plan_id'] : 0;
                if (!$user_id || !$plan_id) {
                    // fallback via membership row
                    global $wpdb; $t=$wpdb->prefix.'cfp_memberships';
                    $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE stripe_subscription_id=%s", $sub_id), ARRAY_A);
                    if ($m) { $user_id=(int)$m['user_id']; $plan_id=(int)$m['plan_id']; }
                }
                if (!$user_id || !$plan_id) continue;
                $plan = self::get_plan($plan_id);
                if (!$plan) continue;
                $credits = (int)$plan['credits_per_period'];
                if ($credits > 0) {
                    \ClassFlowPro\Packages\Manager::grant_package($user_id, 'Membership Credits', $credits, 0, 'usd', null);
                }
            }
        } catch (\Throwable $e) {
            error_log('[CFP] grant_periodic_credits error: ' . $e->getMessage());
        }
    }
}

