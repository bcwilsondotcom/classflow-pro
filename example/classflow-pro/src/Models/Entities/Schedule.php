<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class Schedule {
    private ?int $id;
    private int $classId;
    private int $instructorId;
    private ?int $locationId;
    private \DateTime $startTime;
    private \DateTime $endTime;
    private ?string $recurrenceRule;
    private ?\DateTime $recurrenceEnd;
    private ?int $capacityOverride;
    private ?float $priceOverride;
    private string $bookingType;
    private string $status;
    private array $meta;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        int $classId,
        int $instructorId,
        \DateTime $startTime,
        \DateTime $endTime,
        string $status = 'scheduled'
    ) {
        $this->id = null;
        $this->classId = $classId;
        $this->instructorId = $instructorId;
        $this->locationId = null;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->recurrenceRule = null;
        $this->recurrenceEnd = null;
        $this->capacityOverride = null;
        $this->priceOverride = null;
        $this->bookingType = 'group';
        $this->status = $status;
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $schedule = new self(
            (int) $data['class_id'],
            (int) $data['instructor_id'],
            new \DateTime($data['start_time']),
            new \DateTime($data['end_time']),
            $data['status']
        );

        $schedule->id = isset($data['id']) ? (int) $data['id'] : null;
        $schedule->locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
        $schedule->recurrenceRule = $data['recurrence_rule'] ?? null;
        $schedule->recurrenceEnd = !empty($data['recurrence_end']) ? new \DateTime($data['recurrence_end']) : null;
        $schedule->capacityOverride = isset($data['capacity_override']) ? (int) $data['capacity_override'] : null;
        $schedule->priceOverride = isset($data['price_override']) ? (float) $data['price_override'] : null;
        $schedule->bookingType = $data['booking_type'] ?? 'group';
        $schedule->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $schedule->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $schedule->updatedAt = new \DateTime($data['updated_at']);
        }

        return $schedule;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'class_id' => $this->classId,
            'instructor_id' => $this->instructorId,
            'location_id' => $this->locationId,
            'start_time' => $this->startTime->format('Y-m-d H:i:s'),
            'end_time' => $this->endTime->format('Y-m-d H:i:s'),
            'recurrence_rule' => $this->recurrenceRule,
            'recurrence_end' => $this->recurrenceEnd ? $this->recurrenceEnd->format('Y-m-d H:i:s') : null,
            'capacity_override' => $this->capacityOverride,
            'price_override' => $this->priceOverride,
            'booking_type' => $this->bookingType,
            'status' => $this->status,
            'meta' => json_encode($this->meta),
        ];
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getClassId(): int {
        return $this->classId;
    }

    public function getInstructorId(): int {
        return $this->instructorId;
    }

    public function getLocationId(): ?int {
        return $this->locationId;
    }

    public function getStartTime(): \DateTime {
        return $this->startTime;
    }

    public function getEndTime(): \DateTime {
        return $this->endTime;
    }

    public function getRecurrenceRule(): ?string {
        return $this->recurrenceRule;
    }

    public function getRecurrenceEnd(): ?\DateTime {
        return $this->recurrenceEnd;
    }

    public function getCapacityOverride(): ?int {
        return $this->capacityOverride;
    }

    public function getPriceOverride(): ?float {
        return $this->priceOverride;
    }

    public function getBookingType(): string {
        return $this->bookingType;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getMeta(): array {
        return $this->meta;
    }

    // Setters
    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setLocationId(?int $locationId): void {
        $this->locationId = $locationId;
    }

    public function setRecurrenceRule(?string $recurrenceRule): void {
        $this->recurrenceRule = $recurrenceRule;
    }

    public function setRecurrenceEnd(?\DateTime $recurrenceEnd): void {
        $this->recurrenceEnd = $recurrenceEnd;
    }

    public function setCapacityOverride(?int $capacityOverride): void {
        $this->capacityOverride = $capacityOverride;
    }

    public function setPriceOverride(?float $priceOverride): void {
        $this->priceOverride = $priceOverride;
    }

    public function setBookingType(string $bookingType): void {
        $this->bookingType = $bookingType;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function setMeta(array $meta): void {
        $this->meta = $meta;
    }

    public function setStartTime(\DateTime $startTime): void {
        $this->startTime = $startTime;
    }

    public function setEndTime(\DateTime $endTime): void {
        $this->endTime = $endTime;
    }

    // Helper methods
    public function isRecurring(): bool {
        return !empty($this->recurrenceRule);
    }

    public function isActive(): bool {
        return $this->status === 'scheduled';
    }

    public function isCancelled(): bool {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool {
        return $this->status === 'completed';
    }

    public function isUpcoming(): bool {
        return $this->startTime > new \DateTime();
    }

    public function isPast(): bool {
        return $this->endTime < new \DateTime();
    }

    public function isOngoing(): bool {
        $now = new \DateTime();
        return $this->startTime <= $now && $this->endTime >= $now;
    }

    public function getDuration(): int {
        $interval = $this->startTime->diff($this->endTime);
        return ($interval->h * 60) + $interval->i;
    }

    public function getFormattedDateRange(): string {
        if ($this->startTime->format('Y-m-d') === $this->endTime->format('Y-m-d')) {
            return sprintf(
                '%s, %s - %s',
                $this->startTime->format('F j, Y'),
                $this->startTime->format('g:i A'),
                $this->endTime->format('g:i A')
            );
        } else {
            return sprintf(
                '%s - %s',
                $this->startTime->format('F j, Y g:i A'),
                $this->endTime->format('F j, Y g:i A')
            );
        }
    }

    public function isGroupClass(): bool {
        return $this->bookingType === 'group';
    }

    public function isPrivateSession(): bool {
        return $this->bookingType === 'private';
    }

    public function isSemiPrivate(): bool {
        return $this->bookingType === 'semi-private';
    }
}