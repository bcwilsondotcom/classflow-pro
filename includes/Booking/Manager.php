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
        if (!empty($schedule['status']) && $schedule['status'] === 'cancelled') {
            return new WP_Error('cfp_cancelled', __('This class session has been cancelled', 'classflow-pro'), ['status' => 400]);
        }
        
        $user_id = $customer['user_id'] ?? null;
        $email = $customer['email'] ?? '';
        $amount_cents = (int)$schedule['price_cents'];
        // Always use USD for currency
        $currency = 'usd';
        if ($amount_cents <= 0) {
            // Fallback to class price if schedule has no explicit price
            try {
                global $wpdb; $cls=$wpdb->prefix.'cfp_classes';
                $row = $wpdb->get_row($wpdb->prepare("SELECT price_cents, currency FROM $cls WHERE id = %d", (int)$schedule['class_id']), ARRAY_A);
                if ($row) {
                    $amount_cents = max(0, (int)$row['price_cents']);
                }
            } catch (\Throwable $e) {}
        }

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

        // Coupons removed: do not apply local discounts; Stripe promotion codes apply at checkout
        $discount_cents = 0;
        $coupon_id = null;
        $coupon_code = null;

        $table = $wpdb->prefix . 'cfp_bookings';
        
        // START TRANSACTION to ensure atomic capacity check and insert
        $wpdb->query('START TRANSACTION');
        
        try {
            // Lock the schedule row and re-check capacity atomically
            $capacity = (int)$schedule['capacity'];
            $booked = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE schedule_id = %d AND status IN ('pending','confirmed') FOR UPDATE",
                $schedule_id
            ));
            
            if ($booked >= $capacity) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('cfp_full', __('Class is fully booked', 'classflow-pro'), ['status' => 409]);
            }
            
            // Now insert the booking within the transaction
            $wpdb->insert($table, [
                'schedule_id' => $schedule_id,
                'user_id' => $user_id,
                'customer_email' => $email,
                'status' => $status,
                'payment_intent_id' => $payment_intent_id,
                'payment_status' => $amount_cents > 0 ? 'requires_payment' : 'paid',
                'credits_used' => $credits_used,
                'amount_cents' => $amount_cents,
                'discount_cents' => 0,
                'currency' => $currency,
                'coupon_id' => null,
                'coupon_code' => null,
                'metadata' => wp_json_encode(['name' => $customer['name'] ?? '']),
            ], [
                '%d','%d','%s','%s','%s','%d','%d','%d','%s','%d','%s','%s'
            ]);

            $booking_id = (int)$wpdb->insert_id;
            
            if (!$booking_id) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('cfp_booking_failed', __('Failed to create booking', 'classflow-pro'), ['status' => 500]);
            }
            
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('cfp_booking_error', __('Booking error: ', 'classflow-pro') . $e->getMessage(), ['status' => 500]);
        }

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

        // Always use USD for payment intent currency
        $cur = 'usd';
        $intent = StripeGateway::create_intent([
            'amount_cents' => (int)$booking['amount_cents'],
            'currency' => $cur,
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
        
        // Check cancellation policy
        $policy_enabled = \ClassFlowPro\Admin\Settings::get('cancellation_policy_enabled', false);
        $policy_type = \ClassFlowPro\Admin\Settings::get('cancellation_policy_type', 'flexible');
        $window_hours = (int)\ClassFlowPro\Admin\Settings::get('cancellation_window_hours', 0);
        $is_late_cancel = false;
        
        if ($window_hours > 0) {
            $deadline = strtotime($schedule['start_time'] . ' UTC') - ($window_hours * 3600);
            if (time() > $deadline) {
                $is_late_cancel = true;
                if ($policy_enabled && $policy_type === 'strict') {
                    return new \WP_Error('cfp_past_deadline', sprintf(__('Cancellations are not allowed within %d hours of class start (strict policy).', 'classflow-pro'), $window_hours), ['status' => 400]);
                }
            }
        }

        // Determine refund amount based on policy
        $refund_enabled = \ClassFlowPro\Admin\Settings::get('refund_policy_enabled', true);
        $refund_type = \ClassFlowPro\Admin\Settings::get('refund_processing_type', 'automatic');
        $refund_percentage = 100; // Default full refund
        
        if ($policy_enabled && $is_late_cancel) {
            switch ($policy_type) {
                case 'moderate':
                    $refund_percentage = 50;
                    break;
                case 'strict':
                    $refund_percentage = 0;
                    break;
                case 'custom':
                    $refund_percentage = (int)\ClassFlowPro\Admin\Settings::get('refund_percentage', 100);
                    break;
            }
        } else {
            $refund_percentage = (int)\ClassFlowPro\Admin\Settings::get('refund_percentage', 100);
        }

        // Process reversal: return credit or refund Stripe
        $new_status = 'canceled';
        if ((int)$booking['credits_used'] > 0) {
            // Return credit based on policy
            if ($refund_percentage > 0) {
                $credits_to_return = ($refund_percentage >= 100) ? 1 : 0; // Only return full credit or none
                if ($credits_to_return > 0) {
                    $pkg_id = \ClassFlowPro\Packages\Manager::grant_package($user_id, 'Returned Credit', $credits_to_return, 0, $booking['currency'], null);
                }
            }
            $new_status = 'canceled';
        } elseif (!empty($booking['payment_intent_id']) && (int)$booking['amount_cents'] > 0) {
            if ($refund_enabled && $refund_percentage > 0) {
                $refund_amount = null;
                if ($refund_percentage < 100) {
                    $refund_amount = (int)(($booking['amount_cents'] * $refund_percentage) / 100);
                }
                
                if ($refund_type === 'automatic' || $refund_type === 'manual') {
                    // Process Stripe refund
                    $refund = \ClassFlowPro\Payments\StripeGateway::refund_intent($booking['payment_intent_id'], $refund_amount);
                    if (is_wp_error($refund)) {
                        return $refund;
                    }
                    $new_status = 'refunded';
                    $transactions = $wpdb->prefix . 'cfp_transactions';
                    $wpdb->insert($transactions, [
                        'user_id' => $booking['user_id'],
                        'booking_id' => $booking['id'],
                        'amount_cents' => -1 * ($refund_amount ?? (int)$booking['amount_cents']),
                        'currency' => $booking['currency'],
                        'type' => 'refund',
                        'processor' => 'stripe',
                        'processor_id' => $refund['id'] ?? '',
                        'status' => 'succeeded',
                        'tax_amount_cents' => 0,
                        'fee_amount_cents' => 0,
                    ], ['%d','%d','%d','%s','%s','%s','%s','%d','%d']);
                } elseif ($refund_type === 'credit_only') {
                    // Issue studio credits instead of refund
                    $credit_amount = (int)(($booking['amount_cents'] * $refund_percentage) / 100);
                    $credits_to_grant = max(1, (int)($credit_amount / 1500)); // Assuming $15 per credit
                    $pkg_id = \ClassFlowPro\Packages\Manager::grant_package($user_id, 'Cancellation Credit', $credits_to_grant, 0, $booking['currency'], null);
                    $new_status = 'canceled';
                }
            } else {
                $new_status = 'canceled';
            }
        } else {
            $new_status = 'canceled';
            // QuickBooks refund receipt
            try { \ClassFlowPro\Accounting\QuickBooks::create_refund_receipt_for_booking((int)$booking['id'], (int)$booking['amount_cents']); } catch (\Throwable $e) {}
        }

        $wpdb->update($bookings, [
            'status' => $new_status,
        ], ['id' => $booking_id], ['%s'], ['%d']);

        try { \ClassFlowPro\Notifications\Mailer::booking_canceled($booking_id, $new_status); } catch (\Throwable $e) { error_log('[CFP] notify booking_canceled error: ' . $e->getMessage()); }

        // Auto-promo from waitlist if seat opened
        try { self::promote_waitlist((int)$booking['schedule_id']); } catch (\Throwable $e) { error_log('[CFP] waitlist promote error: ' . $e->getMessage()); }

        return ['status' => $new_status];
    }

    // Admin-triggered cancellation (e.g., instructor sick). Ignores ownership/window, processes refunds/credits and notifications.
    public static function admin_cancel_booking(int $booking_id, array $opts = [])
    {
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $schedules = $wpdb->prefix . 'cfp_schedules';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE id = %d", $booking_id), ARRAY_A);
        if (!$booking) return new \WP_Error('cfp_not_found', __('Booking not found', 'classflow-pro'), ['status' => 404]);
        if (in_array($booking['status'], ['canceled','refunded'], true)) {
            return ['status' => $booking['status']];
        }
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $schedules WHERE id = %d", $booking['schedule_id']), ARRAY_A);
        if (!$schedule) return new \WP_Error('cfp_not_found', __('Schedule not found', 'classflow-pro'), ['status' => 404]);

        $action = isset($opts['action']) ? (string)$opts['action'] : 'auto'; // auto|refund|credit|cancel
        $new_status = 'canceled';
        $did_credit = false;
        $did_refund = false;
        $can_refund = !empty($booking['payment_intent_id']) && (int)$booking['amount_cents'] > 0;
        $can_credit = (int)$booking['user_id'] > 0;

        if ($action === 'credit') {
            if ($can_credit) {
                \ClassFlowPro\Packages\Manager::grant_package((int)$booking['user_id'], 'Returned Credit', 1, 0, $booking['currency'], null);
                $did_credit = true;
                $new_status = 'canceled';
            }
        } elseif ($action === 'refund') {
            if ($can_refund) {
                $refund = \ClassFlowPro\Payments\StripeGateway::refund_intent($booking['payment_intent_id'], null);
                if (is_wp_error($refund)) {
                    return $refund;
                }
                $did_refund = true;
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
        } elseif ($action === 'cancel') {
            // No financial change, just mark canceled
            $new_status = 'canceled';
        } else { // auto
            if ((int)$booking['credits_used'] > 0 && (int)$booking['user_id'] > 0) {
                \ClassFlowPro\Packages\Manager::grant_package((int)$booking['user_id'], 'Returned Credit', 1, 0, $booking['currency'], null);
                $did_credit = true;
                $new_status = 'canceled';
            } elseif ($can_refund) {
                $refund = \ClassFlowPro\Payments\StripeGateway::refund_intent($booking['payment_intent_id'], null);
                if (is_wp_error($refund)) { return $refund; }
                $did_refund = true;
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
        }

        $wpdb->update($bookings, [ 'status' => $new_status ], ['id' => $booking_id], ['%s'], ['%d']);
        $notify = array_key_exists('notify', $opts) ? (bool)$opts['notify'] : true;
        $note = isset($opts['note']) ? (string)$opts['note'] : '';
        if ($notify) { try { \ClassFlowPro\Notifications\Mailer::booking_canceled($booking_id, $new_status, $note); } catch (\Throwable $e) {} }
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
        // Get earliest queued request
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wl WHERE schedule_id = %d AND status='queued' ORDER BY created_at ASC LIMIT 1", $schedule_id), ARRAY_A);
        if (!$row) return;
        $token = wp_generate_password(32, false, false);
        $hold_mins = (int)\ClassFlowPro\Admin\Settings::get('waitlist_hold_minutes', 60);
        $expires_at = gmdate('Y-m-d H:i:s', time() + max(5, $hold_mins)*60);
        $wpdb->update($wl, [ 'token' => $token, 'status' => 'offered', 'notified_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => $expires_at ], ['id' => (int)$row['id']], ['%s','%s','%s','%s'], ['%d']);
        // Notify via email/SMS with accept/deny links
        try { \ClassFlowPro\Notifications\Mailer::waitlist_offer($schedule_id, $row['email'], $token); } catch (\Throwable $e) { error_log('[CFP] waitlist offer mail error: ' . $e->getMessage()); }
        try { if (!empty($row['user_id'])) \ClassFlowPro\Notifications\Sms::waitlist_offer($schedule_id, (int)$row['user_id'], $token); } catch (\Throwable $e) { error_log('[CFP] waitlist offer sms error: ' . $e->getMessage()); }
    }

    // Expose minimal wrapper for public route to promote next after decline
    public static function promote_waitlist_public(int $schedule_id): void
    {
        self::promote_waitlist($schedule_id);
    }

    // Admin reschedule of a single booking to another schedule (capacity-checked). Sends rescheduled email if notify.
    public static function admin_reschedule_booking(int $booking_id, int $target_schedule_id, array $opts = [])
    {
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE id = %d", $booking_id), ARRAY_A);
        if (!$b) return new \WP_Error('cfp_not_found', __('Booking not found', 'classflow-pro'), ['status' => 404]);
        $old_schedule_id = (int)$b['schedule_id'];
        if ($old_schedule_id === $target_schedule_id) return ['ok' => true];
        $target = self::get_schedule($target_schedule_id);
        if (!$target) return new \WP_Error('cfp_not_found', __('Target schedule not found', 'classflow-pro'), ['status' => 404]);
        if (!empty($target['status']) && $target['status'] === 'cancelled') return new \WP_Error('cfp_invalid', __('Target schedule has been cancelled', 'classflow-pro'), ['status' => 400]);
        // Capacity check
        $booked = self::get_booked_count($target_schedule_id);
        if ($booked >= (int)$target['capacity']) return new \WP_Error('cfp_full', __('Target class is fully booked', 'classflow-pro'), ['status' => 409]);
        // Move booking
        $wpdb->update($bookings, [ 'schedule_id' => $target_schedule_id ], ['id' => $booking_id], ['%d'], ['%d']);
        // Notify
        $notify = array_key_exists('notify', $opts) ? (bool)$opts['notify'] : true;
        if ($notify) {
            try { \ClassFlowPro\Notifications\Mailer::booking_rescheduled($booking_id, $old_schedule_id); } catch (\Throwable $e) {}
        }
        return ['ok' => true];
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
        if (!empty($target['status']) && $target['status'] === 'cancelled') return new \WP_Error('cfp_invalid', __('Target schedule has been cancelled', 'classflow-pro'), ['status' => 400]);
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
