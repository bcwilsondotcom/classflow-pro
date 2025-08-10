<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class Booking {
    private ?int $id;
    private int $scheduleId;
    private int $studentId;
    private string $bookingCode;
    private string $status;
    private string $paymentStatus;
    private float $amount;
    private ?string $notes;
    private array $meta;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        int $scheduleId,
        int $studentId,
        string $bookingCode,
        float $amount,
        string $status = 'pending',
        string $paymentStatus = 'pending'
    ) {
        $this->id = null;
        $this->scheduleId = $scheduleId;
        $this->studentId = $studentId;
        $this->bookingCode = $bookingCode;
        $this->status = $status;
        $this->paymentStatus = $paymentStatus;
        $this->amount = $amount;
        $this->notes = null;
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $booking = new self(
            (int) $data['schedule_id'],
            (int) $data['student_id'],
            $data['booking_code'],
            (float) $data['amount'],
            $data['status'],
            $data['payment_status']
        );

        $booking->id = isset($data['id']) ? (int) $data['id'] : null;
        $booking->notes = $data['notes'] ?? null;
        $booking->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $booking->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $booking->updatedAt = new \DateTime($data['updated_at']);
        }

        return $booking;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'schedule_id' => $this->scheduleId,
            'student_id' => $this->studentId,
            'booking_code' => $this->bookingCode,
            'status' => $this->status,
            'payment_status' => $this->paymentStatus,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'meta' => json_encode($this->meta),
        ];
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getScheduleId(): int {
        return $this->scheduleId;
    }

    public function getStudentId(): int {
        return $this->studentId;
    }

    public function getBookingCode(): string {
        return $this->bookingCode;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getPaymentStatus(): string {
        return $this->paymentStatus;
    }

    public function getAmount(): float {
        return $this->amount;
    }

    public function getNotes(): ?string {
        return $this->notes;
    }

    public function getMeta(): array {
        return $this->meta;
    }

    public function getCreatedAt(): ?\DateTime {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime {
        return $this->updatedAt;
    }

    // Setters
    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function setPaymentStatus(string $paymentStatus): void {
        $this->paymentStatus = $paymentStatus;
    }

    public function setAmount(float $amount): void {
        $this->amount = $amount;
    }

    public function setNotes(?string $notes): void {
        $this->notes = $notes;
    }

    public function setMeta(array $meta): void {
        $this->meta = $meta;
    }

    // Status checks
    public function isPending(): bool {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool {
        return $this->status === 'completed';
    }

    public function isNoShow(): bool {
        return $this->status === 'no_show';
    }

    // Payment status checks
    public function isPaymentPending(): bool {
        return $this->paymentStatus === 'pending';
    }

    public function isPaymentCompleted(): bool {
        return $this->paymentStatus === 'completed';
    }

    public function isPaymentFailed(): bool {
        return $this->paymentStatus === 'failed';
    }

    public function isPaymentRefunded(): bool {
        return $this->paymentStatus === 'refunded';
    }

    public function isPaymentPartial(): bool {
        return $this->paymentStatus === 'partial';
    }

    // Helper methods
    public function canBeCancelled(): bool {
        return in_array($this->status, ['pending', 'confirmed']) && 
               !in_array($this->paymentStatus, ['refunded']);
    }

    public function canBeModified(): bool {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function getFormattedAmount(): string {
        return '$' . number_format($this->amount, 2);
    }

    public function getStatusLabel(): string {
        $labels = [
            'pending' => __('Pending', 'classflow-pro'),
            'confirmed' => __('Confirmed', 'classflow-pro'),
            'cancelled' => __('Cancelled', 'classflow-pro'),
            'completed' => __('Completed', 'classflow-pro'),
            'no_show' => __('No Show', 'classflow-pro'),
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getPaymentStatusLabel(): string {
        $labels = [
            'pending' => __('Payment Pending', 'classflow-pro'),
            'completed' => __('Paid', 'classflow-pro'),
            'failed' => __('Payment Failed', 'classflow-pro'),
            'refunded' => __('Refunded', 'classflow-pro'),
            'partial' => __('Partially Paid', 'classflow-pro'),
        ];

        return $labels[$this->paymentStatus] ?? $this->paymentStatus;
    }
}