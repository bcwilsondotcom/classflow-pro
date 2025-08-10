<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class Package {
    private ?int $id;
    private string $name;
    private ?string $description;
    private int $classesCount;
    private float $price;
    private int $validityDays;
    private array $classRestrictions;
    private string $status;
    private array $meta;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        string $name,
        int $classesCount,
        float $price,
        int $validityDays = 30,
        string $status = 'active'
    ) {
        $this->id = null;
        $this->name = $name;
        $this->description = null;
        $this->classesCount = $classesCount;
        $this->price = $price;
        $this->validityDays = $validityDays;
        $this->classRestrictions = [];
        $this->status = $status;
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $package = new self(
            $data['name'],
            (int) $data['classes_count'],
            (float) $data['price'],
            (int) $data['validity_days'],
            $data['status']
        );

        $package->id = isset($data['id']) ? (int) $data['id'] : null;
        $package->description = $data['description'] ?? null;
        $package->classRestrictions = !empty($data['class_restrictions']) ? json_decode($data['class_restrictions'], true) : [];
        $package->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $package->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $package->updatedAt = new \DateTime($data['updated_at']);
        }

        return $package;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'classes_count' => $this->classesCount,
            'price' => $this->price,
            'validity_days' => $this->validityDays,
            'class_restrictions' => json_encode($this->classRestrictions),
            'status' => $this->status,
            'meta' => json_encode($this->meta),
        ];
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getClassesCount(): int {
        return $this->classesCount;
    }

    public function getPrice(): float {
        return $this->price;
    }

    public function getValidityDays(): int {
        return $this->validityDays;
    }

    public function getClassRestrictions(): array {
        return $this->classRestrictions;
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

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setDescription(?string $description): void {
        $this->description = $description;
    }

    public function setClassesCount(int $classesCount): void {
        $this->classesCount = $classesCount;
    }

    public function setPrice(float $price): void {
        $this->price = $price;
    }

    public function setValidityDays(int $validityDays): void {
        $this->validityDays = $validityDays;
    }

    public function setClassRestrictions(array $classRestrictions): void {
        $this->classRestrictions = $classRestrictions;
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

    public function hasClassRestrictions(): bool {
        return !empty($this->classRestrictions);
    }

    public function isClassAllowed(int $classId): bool {
        if (empty($this->classRestrictions)) {
            return true; // No restrictions means all classes are allowed
        }

        return in_array($classId, $this->classRestrictions);
    }

    public function getPricePerClass(): float {
        return $this->classesCount > 0 ? $this->price / $this->classesCount : 0;
    }

    public function getFormattedPrice(): string {
        return '$' . number_format($this->price, 2);
    }

    public function getFormattedPricePerClass(): string {
        return '$' . number_format($this->getPricePerClass(), 2);
    }

    public function getValidityDescription(): string {
        if ($this->validityDays === 30) {
            return '1 month';
        } elseif ($this->validityDays === 60) {
            return '2 months';
        } elseif ($this->validityDays === 90) {
            return '3 months';
        } elseif ($this->validityDays === 365) {
            return '1 year';
        } else {
            return $this->validityDays . ' days';
        }
    }

    public function getMetaValue(string $key, $default = null) {
        return $this->meta[$key] ?? $default;
    }

    public function setMetaValue(string $key, $value): void {
        $this->meta[$key] = $value;
    }
}