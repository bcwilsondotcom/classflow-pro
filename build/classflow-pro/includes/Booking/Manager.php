<?php
namespace ClassFlowPro\Booking;

use ClassFlowPro\Admin\Settings;
use ClassFlowPro\Packages\Manager as PackageManager;
use ClassFlowPro\Payments\StripeGateway;
use WP_Error;

class Manager
{
    public static function get_schedule(int $schedule_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $schedule_id), ARRAY_A);
        return $row ?: null;
    }

    public static function get_booked_count(int $schedule_id): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_bookings';
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE schedule_id = %d AND status IN ('pending','confirmed')", $schedule_id));
    }

    public static function book(int $schedule_id, array $customer, bool $use_credits = false)
    {
        global $wpdb;
        $schedule = self::get_schedule($schedule_id);
        if (!$schedule) {
            return new WP_Error('cfp_not_found', __('Schedule not found', 'classflow-pro'), ['status' => 404]);
        }
        $capacity = (int)$schedule['capacity'];
        $booked = self::get_booked_count($schedule_id);
        if ($booked >= $capacity) {
            return new WP_Error('cfp_full', __('Class is fully booked', 'classflow-pro'), ['status' => 409]);
        }

        $user_id = $customer['user_id'] ?? null;
        $email = $customer['email'] ?? '';
        $amount_cents = (int)$schedule['price_cents'];
        $currency = $schedule['currency'] ?: Settings::get('currency', 'usd');

        $credits_used = 0;
        $status = 'pending';
        $payment_intent_id = null;

        if ($use_credits && $user_id) {
            $consumed = PackageManager::consume_one_credit($user_id);
            if ($consumed) {
                $credits_used = 1;
                $amount_cents = 0;
                $status = 'confirmed';
            }
        }

        // Apply coupon if provided
        $discount_cents = 0;
        $coupon_id = null;
        $coupon_code = null;
        if (!empty($customer['coupon_code']) && $amount_cents > 0) {
            $coupon = \ClassFlowPro\Coupons\Manager::find_by_code($customer['coupon_code']);
            if ($coupon) {
                $res = \ClassFlowPro\Coupons\Manager::validate_and_discount($coupon, $schedule, (int)($user_id ?: 0), $email ?: null, $amount_cents);
                if (empty($res['error'])) {
                    $discount_cents = (int)$res['discount_cents'];
                    $coupon_id = (int)$coupon['id'];
                    $coupon_code = $coupon['code'];
                    $amount_cents = max(0, $amount_cents - $discount_cents);
                }
            }
        }

        $table = $wpdb->prefix . 'cfp_bookings';
        $wpdb->insert($table, [
            'schedule_id' => $schedule_id,
            'user_id' => $user_id,
            'customer_email' => $email,
            'status' => $status,
            'payment_intent_id' => $payment_intent_id,
            'payment_status' => $amount_cents > 0 ? 'requires_payment' : 'paid',
            'credits_used' => $credits_used,
            'amount_cents' => $amount_cents,
            'discount_cents' => $discount_cents,
            'currency' => $currency,
            'coupon_id' => $coupon_id,
            'coupon_code' => $coupon_code,
            'metadata' => wp_json_encode(['name' => $customer['name'] ?? '']),
        ], [
            '%d','%d','%s','%s','%s','%d','%d','%d','%s','%d','%s','%s'
        ]);

        $booking_id = (int)$wpdb->insert_id;

        if ($status === 'confirmed') {
            try { \ClassFlowPro\Notifications\Mailer::booking_confirmed($booking_id); } catch (\Throwable $e) { error_log('[CFP] notify booking_confirmed error: ' . $e->getMessage()); }
        }

        return [
            'booking_id' => $booking_id,
            'status' => $status,
            'amount_cents' => (int)$amount_cents,
            'currency' => $currency,
        ];
    }

    public static function create_payment_intent(int $booking_id, string $payment_method, string $name, string $email)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $booking_id), ARRAY_A);
        if (!$booking) {
            return new WP_Error('cfp_not_found', __('Booking not found', 'classflow-pro'), ['status' => 404]);
        }
        if ((int)$booking['amount_cents'] <= 0) {
            return new WP_Error('cfp_no_payment_needed', __('No payment needed for this booking', 'classflow-pro'), ['status' => 400]);
        }

        $schedule = self::get_schedule((int)$booking['schedule_id']);
        $class_id = (int)$schedule['class_id'];
        $instructor_id = (int)$schedule['instructor_id'];

        $intent = StripeGateway::create_intent([
            'amount_cents' => (int)$booking['amount_cents'],
            'currency' => $booking['currency'],
            'description' => \ClassFlowPro\Utils\Entities::class_name($class_id) . ' — ' . gmdate('Y-m-d H:i', strtotime($schedule['start_time'])) . ' UTC',
            'receipt_email' => $email,
            'customer_name' => $name,
            'instructor_id' => $instructor_id,
            'metadata' => [
                'booking_id' => (string)$booking_id,
                'schedule_id' => (string)$schedule['id'],
                'class_id' => (string)$class_id,
            ],
        ]);
        if (is_wp_error($intent)) {
            return $intent;
        }

        $wpdb->update($table, [
            'payment_intent_id' => $intent['id'],
        ], ['id' => $booking_id], ['%s'], ['%d']);

        return [
            'payment_intent_client_secret' => $intent['client_secret'],
            'payment_intent_id' => $intent['id'],
        ];
    }

    public static function cancel(int $booking_id, int $user_id)
    {
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $schedules = $wpdb->prefix . 'cfp_schedules';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE id = %d", $booking_id), ARRAY_A);
        if (!$booking) return new \WP_Error('cfp_not_found', __('Booking not found', 'classflow-pro'), ['status' => 404]);
        if ((int)$booking['user_id'] !== (int)$user_id) return new \WP_Error('cfp_forbidden', __('You do not own this booking', 'classflow-pro'), ['status' => 403]);
        if (in_array($booking['status'], ['canceled','refunded'], true)) {
            return ['status' => $booking['status']];
        }
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $schedules WHERE id = %d", $booking['schedule_id']), ARRAY_A);
        if (!$schedule) return new \WP_Error('cfp_not_found', __('Schedule not found', 'classflow-pro'), ['status' => 404]);
        $window_hours = (int)\ClassFlowPro\Admin\Settings::get('cancellation_window_hours', 0);
        if ($window_hours > 0) {
            $deadline = strtotime($schedule['start_time'] . ' UTC') - ($window_hours * 3600);
            if (time() > $deadline) {
                return new \WP_Error('cfp_past_deadline', sprintf(__('Cancellations are allowed until %d hours before start.', 'classflow-pro'), $window_hours), ['status' => 400]);
            }
        }

        // Process reversal: return credit or refund Stripe
        $new_status = 'canceled';
        if ((int)$booking['credits_used'] > 0) {
            // Return one credit
            $pkg_id = \ClassFlowPro\Packages\Manager::grant_package($user_id, 'Returned Credit', 1, 0, $booking['currency'], null);
            $new_status = 'canceled';
        } elseif (!empty($booking['payment_intent_id']) && (int)$booking['amount_cents'] > 0) {
            $refund = \ClassFlowPro\Payments\StripeGateway::refund_intent($booking['payment_intent_id'], null);
            if (is_wp_error($refund)) {
                return $refund;
            }
            $new_status = 'refunded';
            $transactions = $wpdb->prefix . 'cfp_transactions';
            $wpdb->insert($transactions, [
                'user_id' => $booking['user_id'],
                'booking_id' => $booking['id'],
                'amount_cents' => -1 * (int)$booking['amount_cents'],
                'currency' => $booking['currency'],
                'type' => 'refund',
                'processor' => 'stripe',
                'processor_id' => $refund['id'] ?? '',
                'status' => 'succeeded',
                'tax_amount_cents' => 0,
                'fee_amount_cents' => 0,
            ], ['%d','%d','%d','%s','%s','%s','%s','%d','%d']);
        }

        $wpdb->update($bookings, [
            'status' => $new_status,
        ], ['id' => $booking_id], ['%s'], ['%d']);

        try { \ClassFlowPro\Notifications\Mailer::booking_canceled($booking_id, $new_status); } catch (\Throwable $e) { error_log('[CFP] notify booking_canceled error: ' . $e->getMessage()); }

        // Auto-promo from waitlist if seat opened
        try { self::promote_waitlist((int)$booking['schedule_id']); } catch (\Throwable $e) { error_log('[CFP] waitlist promote error: ' . $e->getMessage()); }

        return ['status' => $new_status];
    }

    private static function promote_waitlist(int $schedule_id): void
    {
        global $wpdb;
        $booked = self::get_booked_count($schedule_id);
        $schedule = self::get_schedule($schedule_id);
        if (!$schedule) return;
        if ($booked >= (int)$schedule['capacity']) return;
        $wl = $wpdb->prefix . 'cfp_waitlist';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wl WHERE schedule_id = %d ORDER BY created_at ASC LIMIT 1", $schedule_id), ARRAY_A);
        if (!$row) return;
        try { \ClassFlowPro\Notifications\Mailer::waitlist_open($schedule_id, $row['email']); } catch (\Throwable $e) { error_log('[CFP] waitlist notify error: ' . $e->getMessage()); }
        $wpdb->delete($wl, ['id' => $row['id']], ['%d']);
    }

    public static function reschedule(int $booking_id, int $user_id, int $new_schedule_id)
    {
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $schedules = $wpdb->prefix . 'cfp_schedules';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE id = %d", $booking_id), ARRAY_A);
        if (!$booking) return new \WP_Error('cfp_not_found', __('Booking not found', 'classflow-pro'), ['status' => 404]);
        if ((int)$booking['user_id'] !== (int)$user_id) return new \WP_Error('cfp_forbidden', __('You do not own this booking', 'classflow-pro'), ['status' => 403]);
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM $schedules WHERE id = %d", $booking['schedule_id']), ARRAY_A);
        $target = $wpdb->get_row($wpdb->prepare("SELECT * FROM $schedules WHERE id = %d", $new_schedule_id), ARRAY_A);
        if (!$target) return new \WP_Error('cfp_not_found', __('Target schedule not found', 'classflow-pro'), ['status' => 404]);
        if ($target['start_time'] <= gmdate('Y-m-d H:i:s')) return new \WP_Error('cfp_invalid', __('Target schedule is in the past', 'classflow-pro'), ['status' => 400]);
        if ((int)$target['class_id'] !== (int)$current['class_id']) {
            return new \WP_Error('cfp_invalid', __('Reschedule must be for the same class', 'classflow-pro'), ['status' => 400]);
        }
        $window = (int)\ClassFlowPro\Admin\Settings::get('reschedule_window_hours', 0);
        if ($window > 0) {
            $deadline = strtotime($current['start_time'] . ' UTC') - ($window * 3600);
            if (time() > $deadline) {
                return new \WP_Error('cfp_past_deadline', sprintf(__('Reschedules allowed until %d hours before start.', 'classflow-pro'), $window), ['status' => 400]);
            }
        }
        $booked_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bookings WHERE schedule_id = %d AND status IN ('pending','confirmed')", $new_schedule_id));
        if ($booked_count >= (int)$target['capacity']) {
            return new \WP_Error('cfp_full', __('Target schedule is full', 'classflow-pro'), ['status' => 409]);
        }
        $old_schedule_id = (int)$booking['schedule_id'];
        $wpdb->update($bookings, [ 'schedule_id' => $new_schedule_id ], [ 'id' => $booking_id ], ['%d'], ['%d']);
        try { \ClassFlowPro\Notifications\Mailer::booking_rescheduled($booking_id, $old_schedule_id); } catch (\Throwable $e) { error_log('[CFP] notify booking_rescheduled error: ' . $e->getMessage()); }
        return [ 'status' => 'rescheduled', 'schedule_id' => $new_schedule_id ];
    }
}
