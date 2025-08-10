<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class Instructor {
    private ?int $id;
    private int $userId;
    private ?string $bio;
    private array $specialties;
    private array $availability;
    private ?float $hourlyRate;
    private string $status;
    private array $meta;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        int $userId,
        string $status = 'active'
    ) {
        $this->id = null;
        $this->userId = $userId;
        $this->bio = null;
        $this->specialties = [];
        $this->availability = [];
        $this->hourlyRate = null;
        $this->status = $status;
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $instructor = new self(
            (int) $data['user_id'],
            $data['status']
        );

        $instructor->id = isset($data['id']) ? (int) $data['id'] : null;
        $instructor->bio = $data['bio'] ?? null;
        $instructor->specialties = !empty($data['specialties']) ? json_decode($data['specialties'], true) : [];
        $instructor->availability = !empty($data['availability']) ? json_decode($data['availability'], true) : [];
        $instructor->hourlyRate = isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : null;
        $instructor->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $instructor->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $instructor->updatedAt = new \DateTime($data['updated_at']);
        }

        return $instructor;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'bio' => $this->bio,
            'specialties' => json_encode($this->specialties),
            'availability' => json_encode($this->availability),
            'hourly_rate' => $this->hourlyRate,
            'status' => $this->status,
            'meta' => json_encode($this->meta),
        ];
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getUserId(): int {
        return $this->userId;
    }

    public function getBio(): ?string {
        return $this->bio;
    }

    public function getSpecialties(): array {
        return $this->specialties;
    }

    public function getAvailability(): array {
        return $this->availability;
    }

    public function getHourlyRate(): ?float {
        return $this->hourlyRate;
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

    public function setBio(?string $bio): void {
        $this->bio = $bio;
    }

    public function setSpecialties(array $specialties): void {
        $this->specialties = $specialties;
    }

    public function setAvailability(array $availability): void {
        $this->availability = $availability;
    }

    public function setHourlyRate(?float $hourlyRate): void {
        $this->hourlyRate = $hourlyRate;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function setMeta(array $meta): void {
        $this->meta = $meta;
    }

    // Helper methods
    public function isActive(): bool {
        return $this->status === 'active';
    }

    public function isInactive(): bool {
        return $this->status === 'inactive';
    }

    public function isOnLeave(): bool {
        return $this->status === 'on_leave';
    }

    public function hasSpecialty(string $specialty): bool {
        return in_array($specialty, $this->specialties);
    }

    public function addSpecialty(string $specialty): void {
        if (!in_array($specialty, $this->specialties)) {
            $this->specialties[] = $specialty;
        }
    }

    public function removeSpecialty(string $specialty): void {
        $this->specialties = array_values(array_diff($this->specialties, [$specialty]));
    }

    public function isAvailableOn(string $day, string $time): bool {
        if (!isset($this->availability[$day])) {
            return false;
        }

        foreach ($this->availability[$day] as $slot) {
            if ($time >= $slot['start'] && $time <= $slot['end']) {
                return true;
            }
        }

        return false;
    }

    public function getFormattedHourlyRate(): string {
        return $this->hourlyRate !== null ? '$' . number_format($this->hourlyRate, 2) . '/hr' : 'N/A';
    }

    public function getMetaValue(string $key, $default = null) {
        return $this->meta[$key] ?? $default;
    }

    public function setMetaValue(string $key, $value): void {
        $this->meta[$key] = $value;
    }
}