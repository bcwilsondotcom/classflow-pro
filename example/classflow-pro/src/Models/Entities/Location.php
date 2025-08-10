<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class Location {
    private ?int $id;
    private string $name;
    private ?string $address;
    private ?int $capacity;
    private array $resources;
    private string $status;
    private array $meta;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        string $name,
        string $status = 'active'
    ) {
        $this->id = null;
        $this->name = $name;
        $this->address = null;
        $this->capacity = null;
        $this->resources = [];
        $this->status = $status;
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $location = new self(
            $data['name'],
            $data['status']
        );

        $location->id = isset($data['id']) ? (int) $data['id'] : null;
        $location->address = $data['address'] ?? null;
        $location->capacity = isset($data['capacity']) ? (int) $data['capacity'] : null;
        $location->resources = !empty($data['resources']) ? json_decode($data['resources'], true) : [];
        $location->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $location->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $location->updatedAt = new \DateTime($data['updated_at']);
        }

        return $location;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'capacity' => $this->capacity,
            'resources' => json_encode($this->resources),
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

    public function getAddress(): ?string {
        return $this->address;
    }

    public function getCapacity(): ?int {
        return $this->capacity;
    }

    public function getResources(): array {
        return $this->resources;
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

    public function setAddress(?string $address): void {
        $this->address = $address;
    }

    public function setCapacity(?int $capacity): void {
        $this->capacity = $capacity;
    }

    public function setResources(array $resources): void {
        $this->resources = $resources;
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

    public function hasResource(string $resourceName): bool {
        return isset($this->resources[$resourceName]) && $this->resources[$resourceName] > 0;
    }

    public function getResourceQuantity(string $resourceName): int {
        return $this->resources[$resourceName] ?? 0;
    }

    public function getFullAddress(): string {
        return $this->address ?: $this->name;
    }
}