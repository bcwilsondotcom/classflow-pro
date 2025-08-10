<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class Student {
    private ?int $id;
    private int $userId;
    private ?array $emergencyContact;
    private ?string $medicalNotes;
    private array $preferences;
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
        $this->emergencyContact = null;
        $this->medicalNotes = null;
        $this->preferences = [];
        $this->status = $status;
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $student = new self(
            (int) $data['user_id'],
            $data['status']
        );

        $student->id = isset($data['id']) ? (int) $data['id'] : null;
        $student->emergencyContact = !empty($data['emergency_contact']) ? json_decode($data['emergency_contact'], true) : null;
        $student->medicalNotes = $data['medical_notes'] ?? null;
        $student->preferences = !empty($data['preferences']) ? json_decode($data['preferences'], true) : [];
        $student->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $student->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $student->updatedAt = new \DateTime($data['updated_at']);
        }

        return $student;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'emergency_contact' => json_encode($this->emergencyContact),
            'medical_notes' => $this->medicalNotes,
            'preferences' => json_encode($this->preferences),
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

    public function getEmergencyContact(): ?array {
        return $this->emergencyContact;
    }

    public function getMedicalNotes(): ?string {
        return $this->medicalNotes;
    }

    public function getPreferences(): array {
        return $this->preferences;
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

    public function setEmergencyContact(?array $emergencyContact): void {
        $this->emergencyContact = $emergencyContact;
    }

    public function setMedicalNotes(?string $medicalNotes): void {
        $this->medicalNotes = $medicalNotes;
    }

    public function setPreferences(array $preferences): void {
        $this->preferences = $preferences;
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

    public function isSuspended(): bool {
        return $this->status === 'suspended';
    }

    public function isInactive(): bool {
        return $this->status === 'inactive';
    }

    public function hasEmergencyContact(): bool {
        return !empty($this->emergencyContact);
    }

    public function hasMedicalNotes(): bool {
        return !empty($this->medicalNotes);
    }

    public function getPreference(string $key, $default = null) {
        return $this->preferences[$key] ?? $default;
    }

    public function setPreference(string $key, $value): void {
        $this->preferences[$key] = $value;
    }

    public function getMetaValue(string $key, $default = null) {
        return $this->meta[$key] ?? $default;
    }

    public function setMetaValue(string $key, $value): void {
        $this->meta[$key] = $value;
    }
}