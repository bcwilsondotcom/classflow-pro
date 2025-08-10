<?php
declare(strict_types=1);

namespace ClassFlowPro\Services;

use ClassFlowPro\Models\Entities\Booking;
use ClassFlowPro\Models\Entities\Payment;
use ClassFlowPro\Models\Repositories\PaymentRepository;
use ClassFlowPro\Core\Settings;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Exception\ApiErrorException;

class PaymentService {
    private PaymentRepository $paymentRepository;
    private Settings $settings;
    private Container $container;
    private ?string $stripeSecretKey = null;
    private ?string $stripePublishableKey = null;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->paymentRepository = new PaymentRepository();
        $this->settings = new Settings();
        
        $this->initializeStripe();
    }

    private function initializeStripe(): void {
        $mode = $this->settings->get('payment.stripe_mode', 'test');
        
        if ($mode === 'live') {
            $this->stripeSecretKey = $this->settings->get('payment.stripe_live_secret_key');
            $this->stripePublishableKey = $this->settings->get('payment.stripe_live_publishable_key');
        } else {
            $this->stripeSecretKey = $this->settings->get('payment.stripe_test_secret_key');
            $this->stripePublishableKey = $this->settings->get('payment.stripe_test_publishable_key');
        }
        
        if ($this->stripeSecretKey) {
            Stripe::setApiKey($this->stripeSecretKey);
        }
    }

    public function createPaymentIntent(Booking $booking, array $metadata = []): array {
        if (!$this->stripeSecretKey) {
            throw new \RuntimeException(__('Stripe is not configured.', 'classflow-pro'));
        }
        
        try {
            $amount = $this->calculatePaymentAmount($booking);
            $currency = $this->settings->get('general.currency', 'USD');
            
            // Get schedule and instructor info for potential direct charges
            $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
            $instructorId = $schedule ? $schedule->getInstructorId() : null;
            $instructorAccountId = $instructorId ? get_user_meta($instructorId, 'stripe_connect_account_id', true) : null;
            
            // Calculate platform fee if using connected accounts
            $platformFeeAmount = null;
            if ($instructorAccountId && $this->settings->get('payment.use_connected_accounts', false)) {
                $platformFeePercent = (float) $this->settings->get('payment.platform_fee_percentage', 20);
                $platformFeeAmount = (int) ($amount * ($platformFeePercent / 100) * 100); // Convert to cents
            }
            
            // Create payment intent
            $intentParams = [
                'amount' => (int) ($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'description' => $this->getPaymentDescription($booking),
                'metadata' => array_merge([
                    'booking_id' => $booking->getId(),
                    'booking_code' => $booking->getBookingCode(),
                    'student_id' => $booking->getStudentId(),
                    'instructor_id' => $instructorId,
                ], $metadata),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ];
            
            // If instructor has connected account, set up for direct charge with platform fee
            if ($instructorAccountId && $platformFeeAmount !== null) {
                $intentParams['application_fee_amount'] = $platformFeeAmount;
                $intentParams['transfer_data'] = [
                    'destination' => $instructorAccountId,
                ];
            }
            
            $intent = PaymentIntent::create($intentParams);
            
            // Create payment record
            $payment = new Payment(
                $booking->getId(),
                null,
                $amount,
                $currency,
                'stripe',
                $intent->id,
                'pending'
            );
            
            $payment->setGatewayResponse([
                'client_secret' => $intent->client_secret,
                'publishable_key' => $this->stripePublishableKey,
            ]);
            
            if ($instructorAccountId) {
                $payment->setMetaValue('instructor_account_id', $instructorAccountId);
                $payment->setMetaValue('platform_fee', $platformFeeAmount / 100);
            }
            
            $this->paymentRepository->save($payment);
            
            return [
                'payment_id' => $payment->getId(),
                'client_secret' => $intent->client_secret,
                'publishable_key' => $this->stripePublishableKey,
                'amount' => $amount,
                'currency' => $currency,
            ];
            
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(
                sprintf(__('Payment error: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }

    public function confirmPayment(string $paymentIntentId): Payment {
        if (!$this->stripeSecretKey) {
            throw new \RuntimeException(__('Stripe is not configured.', 'classflow-pro'));
        }
        
        try {
            // Retrieve payment intent from Stripe
            $intent = PaymentIntent::retrieve($paymentIntentId);
            
            // Find payment record
            $payment = $this->paymentRepository->findByTransactionId($paymentIntentId);
            if (!$payment) {
                throw new \RuntimeException(__('Payment record not found.', 'classflow-pro'));
            }
            
            // Update payment status based on intent status
            switch ($intent->status) {
                case 'succeeded':
                    $payment->setStatus('completed');
                    
                    // Update booking payment status
                    $booking = $this->container->get('booking_repository')->find($payment->getBookingId());
                    if ($booking) {
                        $booking->setPaymentStatus('completed');
                        $this->container->get('booking_repository')->save($booking);
                        
                        // Send payment confirmation
                        $this->container->get('notification_service')->sendPaymentConfirmation($booking);
                    }
                    break;
                    
                case 'processing':
                    $payment->setStatus('processing');
                    break;
                    
                case 'requires_payment_method':
                case 'requires_confirmation':
                case 'requires_action':
                    $payment->setStatus('pending');
                    break;
                    
                case 'canceled':
                    $payment->setStatus('cancelled');
                    break;
                    
                default:
                    $payment->setStatus('failed');
            }
            
            // Store full response
            $payment->setGatewayResponse($intent->toArray());
            $this->paymentRepository->save($payment);
            
            // Trigger action
            do_action('classflow_pro_payment_confirmed', $payment, $intent);
            
            return $payment;
            
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(
                sprintf(__('Payment confirmation error: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }

    public function processRefund(Booking $booking, float $amount = null, string $reason = ''): Payment {
        if (!$this->stripeSecretKey) {
            throw new \RuntimeException(__('Stripe is not configured.', 'classflow-pro'));
        }
        
        // Find original payment
        $originalPayment = $this->paymentRepository->findCompletedPaymentForBooking($booking->getId());
        if (!$originalPayment) {
            throw new \RuntimeException(__('No completed payment found for this booking.', 'classflow-pro'));
        }
        
        try {
            // Calculate refund amount
            $refundAmount = $amount ?? $originalPayment->getAmount();
            
            // Create refund in Stripe
            $refund = Refund::create([
                'payment_intent' => $originalPayment->getTransactionId(),
                'amount' => (int) ($refundAmount * 100), // Convert to cents
                'reason' => $this->mapRefundReason($reason),
                'metadata' => [
                    'booking_id' => $booking->getId(),
                    'booking_code' => $booking->getBookingCode(),
                ],
            ]);
            
            // Create refund payment record
            $refundPayment = new Payment(
                $booking->getId(),
                null,
                -$refundAmount, // Negative amount for refund
                $originalPayment->getCurrency(),
                'stripe',
                $refund->id,
                'completed'
            );
            
            $refundPayment->setMeta([
                'type' => 'refund',
                'original_payment_id' => $originalPayment->getId(),
                'reason' => $reason,
            ]);
            
            $refundPayment->setGatewayResponse($refund->toArray());
            $this->paymentRepository->save($refundPayment);
            
            // Update booking payment status
            if ($refundAmount >= $originalPayment->getAmount()) {
                $booking->setPaymentStatus('refunded');
            } else {
                $booking->setPaymentStatus('partial_refund');
            }
            
            $this->container->get('booking_repository')->save($booking);
            
            // Send refund notification
            $this->container->get('notification_service')->sendRefundConfirmation($booking, $refundAmount);
            
            // Trigger action
            do_action('classflow_pro_payment_refunded', $refundPayment, $booking);
            
            return $refundPayment;
            
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(
                sprintf(__('Refund error: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }

    public function adjustPayment(Booking $booking, float $newAmount): void {
        $originalPayment = $this->paymentRepository->findCompletedPaymentForBooking($booking->getId());
        if (!$originalPayment) {
            // No payment yet, just update booking amount
            return;
        }
        
        $difference = $newAmount - $originalPayment->getAmount();
        
        if ($difference > 0) {
            // Need additional payment
            $this->createAdditionalPayment($booking, $difference);
        } elseif ($difference < 0) {
            // Process partial refund
            $this->processRefund($booking, abs($difference), 'price_adjustment');
        }
    }

    public function getPaymentHistory(int $bookingId): array {
        return $this->paymentRepository->findByBooking($bookingId);
    }

    public function getRevenueReport(\DateTime $startDate, \DateTime $endDate): array {
        $payments = $this->paymentRepository->findByDateRange($startDate, $endDate, ['completed']);
        
        $totalRevenue = 0;
        $refundedAmount = 0;
        $netRevenue = 0;
        $paymentsByGateway = [];
        $paymentsByCurrency = [];
        
        foreach ($payments as $payment) {
            if ($payment->getAmount() > 0) {
                $totalRevenue += $payment->getAmount();
                
                // Group by gateway
                $gateway = $payment->getGateway();
                if (!isset($paymentsByGateway[$gateway])) {
                    $paymentsByGateway[$gateway] = 0;
                }
                $paymentsByGateway[$gateway] += $payment->getAmount();
                
                // Group by currency
                $currency = $payment->getCurrency();
                if (!isset($paymentsByCurrency[$currency])) {
                    $paymentsByCurrency[$currency] = 0;
                }
                $paymentsByCurrency[$currency] += $payment->getAmount();
            } else {
                $refundedAmount += abs($payment->getAmount());
            }
        }
        
        $netRevenue = $totalRevenue - $refundedAmount;
        
        return [
            'total_revenue' => $totalRevenue,
            'refunded_amount' => $refundedAmount,
            'net_revenue' => $netRevenue,
            'payments_count' => count($payments),
            'by_gateway' => $paymentsByGateway,
            'by_currency' => $paymentsByCurrency,
            'average_transaction' => count($payments) > 0 ? $totalRevenue / count($payments) : 0,
        ];
    }

    public function getStripePublishableKey(): ?string {
        return $this->stripePublishableKey;
    }

    public function isPaymentRequired(Booking $booking): bool {
        if (!$this->settings->get('payment.enabled', true)) {
            return false;
        }
        
        if (!$this->settings->get('payment.require_payment', true)) {
            return false;
        }
        
        return $booking->getAmount() > 0;
    }

    private function calculatePaymentAmount(Booking $booking): float {
        $fullAmount = $booking->getAmount();
        
        // Check if partial payment is allowed
        if ($this->settings->get('payment.allow_partial_payment', false)) {
            $percentage = (float) $this->settings->get('payment.partial_payment_percentage', 50);
            return $fullAmount * ($percentage / 100);
        }
        
        return $fullAmount;
    }

    private function getPaymentDescription(Booking $booking): string {
        // Get class and schedule details
        $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
        $class = $this->container->get('class_repository')->find($schedule->getClassId());
        
        return sprintf(
            __('Booking for %s on %s', 'classflow-pro'),
            $class->getName(),
            $schedule->getStartTime()->format('F j, Y')
        );
    }

    private function mapRefundReason(string $reason): string {
        $reasonMap = [
            'requested_by_customer' => 'requested_by_customer',
            'duplicate' => 'duplicate',
            'fraudulent' => 'fraudulent',
            'price_adjustment' => 'requested_by_customer',
        ];
        
        return $reasonMap[$reason] ?? 'requested_by_customer';
    }

    private function createAdditionalPayment(Booking $booking, float $amount): array {
        // Create a new payment intent for the additional amount
        $tempBooking = clone $booking;
        $tempBooking->setAmount($amount);
        
        return $this->createPaymentIntent($tempBooking, [
            'type' => 'additional_payment',
            'reason' => 'price_adjustment',
        ]);
    }

    public function handleWebhook(array $payload, string $signature): void {
        $webhookSecret = $this->settings->get('payment.stripe_webhook_secret');
        
        if (!$webhookSecret) {
            throw new \RuntimeException(__('Webhook secret not configured.', 'classflow-pro'));
        }
        
        try {
            // Verify webhook signature
            $event = \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $webhookSecret
            );
            
            // Handle the event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->confirmPayment($event->data->object->id);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailure($event->data->object);
                    break;
                    
                case 'charge.refunded':
                    $this->handleRefundWebhook($event->data->object);
                    break;
            }
            
            // Trigger action for custom handling
            do_action('classflow_pro_stripe_webhook', $event);
            
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf(__('Webhook error: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }

    private function handlePaymentFailure($paymentIntent): void {
        $payment = $this->paymentRepository->findByTransactionId($paymentIntent->id);
        if ($payment) {
            $payment->setStatus('failed');
            $payment->setGatewayResponse($paymentIntent->toArray());
            $this->paymentRepository->save($payment);
            
            // Update booking
            $booking = $this->container->get('booking_repository')->find($payment->getBookingId());
            if ($booking) {
                $booking->setPaymentStatus('failed');
                $this->container->get('booking_repository')->save($booking);
                
                // Send failure notification
                $this->container->get('notification_service')->sendPaymentFailed($booking);
            }
        }
    }

    private function handleRefundWebhook($charge): void {
        // Find the original payment by charge ID
        foreach ($charge->refunds->data as $refund) {
            $refundPayment = $this->paymentRepository->findByTransactionId($refund->id);
            if (!$refundPayment) {
                // Create refund record if it doesn't exist
                // This handles refunds initiated outside of our system
                // Implementation would go here
            }
        }
    }
    
    // Stripe Connect Methods
    
    public function createConnectedAccount(int $instructorId): array {
        if (!$this->stripeSecretKey) {
            throw new \RuntimeException(__('Stripe is not configured.', 'classflow-pro'));
        }
        
        $instructor = get_userdata($instructorId);
        if (!$instructor) {
            throw new \RuntimeException(__('Instructor not found.', 'classflow-pro'));
        }
        
        try {
            // Create connected account
            $account = \Stripe\Account::create([
                'type' => 'express',
                'country' => $this->settings->get('general.country_code', 'US'),
                'email' => $instructor->user_email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_type' => 'individual',
                'business_profile' => [
                    'name' => $instructor->display_name,
                    'product_description' => __('Class instruction services', 'classflow-pro'),
                ],
                'metadata' => [
                    'instructor_id' => $instructorId,
                    'platform' => 'classflow_pro',
                ],
            ]);
            
            // Save account ID to instructor meta
            update_user_meta($instructorId, 'stripe_connect_account_id', $account->id);
            
            return [
                'account_id' => $account->id,
                'details_submitted' => $account->details_submitted,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new \RuntimeException(
                sprintf(__('Failed to create connected account: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }
    
    public function createAccountOnboardingLink(int $instructorId, string $returnUrl, string $refreshUrl): string {
        $accountId = get_user_meta($instructorId, 'stripe_connect_account_id', true);
        if (!$accountId) {
            throw new \RuntimeException(__('No Stripe account found for this instructor.', 'classflow-pro'));
        }
        
        try {
            $accountLink = \Stripe\AccountLink::create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);
            
            return $accountLink->url;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new \RuntimeException(
                sprintf(__('Failed to create onboarding link: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }
    
    public function getConnectedAccount(int $instructorId): ?array {
        $accountId = get_user_meta($instructorId, 'stripe_connect_account_id', true);
        if (!$accountId) {
            return null;
        }
        
        try {
            $account = \Stripe\Account::retrieve($accountId);
            
            return [
                'account_id' => $account->id,
                'details_submitted' => $account->details_submitted,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'email' => $account->email,
                'country' => $account->country,
                'created' => $account->created,
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return null;
        }
    }
    
    public function createAccountDashboardLink(int $instructorId): string {
        $accountId = get_user_meta($instructorId, 'stripe_connect_account_id', true);
        if (!$accountId) {
            throw new \RuntimeException(__('No Stripe account found for this instructor.', 'classflow-pro'));
        }
        
        try {
            $loginLink = \Stripe\Account::createLoginLink($accountId);
            return $loginLink->url;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new \RuntimeException(
                sprintf(__('Failed to create dashboard link: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }
    
    public function disconnectAccount(int $instructorId): bool {
        $accountId = get_user_meta($instructorId, 'stripe_connect_account_id', true);
        if (!$accountId) {
            return false;
        }
        
        try {
            // Note: Stripe doesn't allow deleting connected accounts via API
            // We just remove the association from our system
            delete_user_meta($instructorId, 'stripe_connect_account_id');
            
            // Trigger action for cleanup
            do_action('classflow_pro_stripe_account_disconnected', $instructorId, $accountId);
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function createTransferToInstructor(Booking $booking, float $amount, string $description = ''): array {
        if (!$this->stripeSecretKey) {
            throw new \RuntimeException(__('Stripe is not configured.', 'classflow-pro'));
        }
        
        // Get instructor from booking
        $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
        if (!$schedule) {
            throw new \RuntimeException(__('Schedule not found.', 'classflow-pro'));
        }
        
        $instructorId = $schedule->getInstructorId();
        $accountId = get_user_meta($instructorId, 'stripe_connect_account_id', true);
        
        if (!$accountId) {
            throw new \RuntimeException(__('Instructor does not have a connected Stripe account.', 'classflow-pro'));
        }
        
        try {
            // Create transfer
            $transfer = \Stripe\Transfer::create([
                'amount' => (int) ($amount * 100), // Convert to cents
                'currency' => strtolower($this->settings->get('general.currency', 'USD')),
                'destination' => $accountId,
                'description' => $description ?: sprintf(
                    __('Payment for booking %s', 'classflow-pro'),
                    $booking->getBookingCode()
                ),
                'metadata' => [
                    'booking_id' => $booking->getId(),
                    'booking_code' => $booking->getBookingCode(),
                    'instructor_id' => $instructorId,
                ],
            ]);
            
            // Record the transfer
            $payment = new Payment(
                $booking->getId(),
                null,
                -$amount, // Negative amount for transfer out
                $this->settings->get('general.currency', 'USD'),
                'stripe',
                $transfer->id,
                'completed'
            );
            
            $payment->setMeta([
                'type' => 'transfer',
                'instructor_id' => $instructorId,
                'account_id' => $accountId,
                'transfer_id' => $transfer->id,
            ]);
            
            $this->paymentRepository->save($payment);
            
            // Trigger action
            do_action('classflow_pro_instructor_payment_sent', $transfer, $booking, $instructorId);
            
            return [
                'transfer_id' => $transfer->id,
                'amount' => $amount,
                'currency' => $transfer->currency,
                'status' => $transfer->status,
                'created' => $transfer->created,
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new \RuntimeException(
                sprintf(__('Transfer failed: %s', 'classflow-pro'), $e->getMessage())
            );
        }
    }
    
    public function calculateInstructorPayout(Booking $booking): array {
        $amount = $booking->getAmount();
        
        // Get platform fee percentage
        $platformFeePercent = (float) $this->settings->get('payment.platform_fee_percentage', 20);
        $platformFee = $amount * ($platformFeePercent / 100);
        
        // Get Stripe processing fee (2.9% + $0.30)
        $stripeFeePercent = 2.9;
        $stripeFeeFixed = 0.30;
        $stripeFee = ($amount * ($stripeFeePercent / 100)) + $stripeFeeFixed;
        
        // Calculate instructor payout
        $instructorPayout = $amount - $platformFee - $stripeFee;
        
        return [
            'booking_amount' => $amount,
            'platform_fee' => round($platformFee, 2),
            'platform_fee_percent' => $platformFeePercent,
            'stripe_fee' => round($stripeFee, 2),
            'instructor_payout' => round($instructorPayout, 2),
        ];
    }
    
    public function processInstructorPayout(Booking $booking): array {
        // Only process payouts for completed bookings with completed payments
        if ($booking->getStatus() !== 'completed' || $booking->getPaymentStatus() !== 'completed') {
            throw new \RuntimeException(__('Booking must be completed with payment before processing payout.', 'classflow-pro'));
        }
        
        // Check if payout already processed
        $existingTransfers = $this->paymentRepository->findByBookingAndType($booking->getId(), 'transfer');
        if (!empty($existingTransfers)) {
            throw new \RuntimeException(__('Payout already processed for this booking.', 'classflow-pro'));
        }
        
        // Calculate payout
        $payoutDetails = $this->calculateInstructorPayout($booking);
        
        // Create transfer
        return $this->createTransferToInstructor(
            $booking,
            $payoutDetails['instructor_payout'],
            sprintf(
                __('Payout for class on %s', 'classflow-pro'),
                $booking->getCreatedAt()->format('M j, Y')
            )
        );
    }
    
    public function getInstructorBalance(int $instructorId): array {
        $accountId = get_user_meta($instructorId, 'stripe_connect_account_id', true);
        if (!$accountId) {
            return [
                'available' => 0,
                'pending' => 0,
                'currency' => $this->settings->get('general.currency', 'USD'),
            ];
        }
        
        try {
            $balance = \Stripe\Balance::retrieve([
                'stripe_account' => $accountId,
            ]);
            
            $available = 0;
            $pending = 0;
            $currency = strtoupper($this->settings->get('general.currency', 'USD'));
            
            foreach ($balance->available as $balanceItem) {
                if (strtoupper($balanceItem->currency) === $currency) {
                    $available = $balanceItem->amount / 100; // Convert from cents
                }
            }
            
            foreach ($balance->pending as $balanceItem) {
                if (strtoupper($balanceItem->currency) === $currency) {
                    $pending = $balanceItem->amount / 100; // Convert from cents
                }
            }
            
            return [
                'available' => $available,
                'pending' => $pending,
                'currency' => $currency,
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'available' => 0,
                'pending' => 0,
                'currency' => $this->settings->get('general.currency', 'USD'),
                'error' => $e->getMessage(),
            ];
        }
    }
    
    public function handleConnectWebhook(array $event): void {
        switch ($event['type']) {
            case 'account.updated':
                $this->handleAccountUpdated($event['data']['object']);
                break;
                
            case 'account.application.deauthorized':
                $this->handleAccountDeauthorized($event['data']['object']);
                break;
                
            case 'transfer.created':
            case 'transfer.updated':
                $this->handleTransferUpdate($event['data']['object']);
                break;
                
            case 'payout.created':
            case 'payout.paid':
            case 'payout.failed':
                $this->handlePayoutUpdate($event['data']['object']);
                break;
        }
        
        // Trigger action for custom handling
        do_action('classflow_pro_stripe_connect_webhook', $event);
    }
    
    private function handleAccountUpdated($account): void {
        // Find instructor by account ID
        $users = get_users([
            'meta_key' => 'stripe_connect_account_id',
            'meta_value' => $account['id'],
            'number' => 1,
        ]);
        
        if (!empty($users)) {
            $instructor = $users[0];
            
            // Update account status
            update_user_meta($instructor->ID, 'stripe_connect_charges_enabled', $account['charges_enabled']);
            update_user_meta($instructor->ID, 'stripe_connect_payouts_enabled', $account['payouts_enabled']);
            update_user_meta($instructor->ID, 'stripe_connect_details_submitted', $account['details_submitted']);
            
            // Trigger action
            do_action('classflow_pro_instructor_stripe_account_updated', $instructor->ID, $account);
        }
    }
    
    private function handleAccountDeauthorized($account): void {
        // Find and disconnect instructor
        $users = get_users([
            'meta_key' => 'stripe_connect_account_id',
            'meta_value' => $account['id'],
            'number' => 1,
        ]);
        
        if (!empty($users)) {
            $this->disconnectAccount($users[0]->ID);
        }
    }
    
    private function handleTransferUpdate($transfer): void {
        // Update transfer record if exists
        $payment = $this->paymentRepository->findByTransactionId($transfer['id']);
        if ($payment) {
            $payment->setStatus($transfer['status']);
            $payment->setGatewayResponse($transfer);
            $this->paymentRepository->save($payment);
        }
    }
    
    private function handlePayoutUpdate($payout): void {
        // Log payout status for tracking
        do_action('classflow_pro_instructor_payout_status', $payout);
    }
}