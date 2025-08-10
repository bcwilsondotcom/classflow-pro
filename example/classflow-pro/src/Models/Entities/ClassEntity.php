<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class ClassEntity {
    private ?int $id;
    private string $name;
    private string $slug;
    private ?string $description;
    private ?int $categoryId;
    private int $duration;
    private int $capacity;
    private float $price;
    private string $status;
    private ?int $featuredImageId;
    private array $galleryIds;
    private array $prerequisites;
    private ?string $skillLevel;
    private string $schedulingType;
    private array $meta;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        string $name,
        string $slug,
        int $duration = 60,
        int $capacity = 10,
        float $price = 0.0,
        string $status = 'active'
    ) {
        $this->id = null;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = null;
        $this->categoryId = null;
        $this->duration = $duration;
        $this->capacity = $capacity;
        $this->price = $price;
        $this->status = $status;
        $this->featuredImageId = null;
        $this->galleryIds = [];
        $this->prerequisites = [];
        $this->skillLevel = null;
        $this->schedulingType = 'fixed';
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $class = new self(
            $data['name'],
            $data['slug'],
            (int) $data['duration'],
            (int) $data['capacity'],
            (float) $data['price'],
            $data['status']
        );

        $class->id = isset($data['id']) ? (int) $data['id'] : null;
        $class->description = $data['description'] ?? null;
        $class->categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $class->featuredImageId = isset($data['featured_image_id']) ? (int) $data['featured_image_id'] : null;
        $class->galleryIds = !empty($data['gallery_ids']) ? json_decode($data['gallery_ids'], true) : [];
        $class->prerequisites = !empty($data['prerequisites']) ? json_decode($data['prerequisites'], true) : [];
        $class->skillLevel = $data['skill_level'] ?? null;
        $class->schedulingType = $data['scheduling_type'] ?? 'fixed';
        $class->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $class->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $class->updatedAt = new \DateTime($data['updated_at']);
        }

        return $class;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'duration' => $this->duration,
            'capacity' => $this->capacity,
            'price' => $this->price,
            'status' => $this->status,
            'featured_image_id' => $this->featuredImageId,
            'gallery_ids' => json_encode($this->galleryIds),
            'prerequisites' => json_encode($this->prerequisites),
            'skill_level' => $this->skillLevel,
            'scheduling_type' => $this->schedulingType,
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

    public function getSlug(): string {
        return $this->slug;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getCategoryId(): ?int {
        return $this->categoryId;
    }

    public function getDuration(): int {
        return $this->duration;
    }

    public function getCapacity(): int {
        return $this->capacity;
    }

    public function getPrice(): float {
        return $this->price;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getFeaturedImageId(): ?int {
        return $this->featuredImageId;
    }

    public function getGalleryIds(): array {
        return $this->galleryIds;
    }

    public function getPrerequisites(): array {
        return $this->prerequisites;
    }

    public function getSkillLevel(): ?string {
        return $this->skillLevel;
    }

    public function getSchedulingType(): string {
        return $this->schedulingType;
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

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setSlug(string $slug): void {
        $this->slug = $slug;
    }

    public function setDescription(?string $description): void {
        $this->description = $description;
    }

    public function setCategoryId(?int $categoryId): void {
        $this->categoryId = $categoryId;
    }

    public function setDuration(int $duration): void {
        $this->duration = $duration;
    }

    public function setCapacity(int $capacity): void {
        $this->capacity = $capacity;
    }

    public function setPrice(float $price): void {
        $this->price = $price;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function setFeaturedImageId(?int $featuredImageId): void {
        $this->featuredImageId = $featuredImageId;
    }

    public function setGalleryIds(array $galleryIds): void {
        $this->galleryIds = $galleryIds;
    }

    public function setPrerequisites(array $prerequisites): void {
        $this->prerequisites = $prerequisites;
    }

    public function setSkillLevel(?string $skillLevel): void {
        $this->skillLevel = $skillLevel;
    }

    public function setSchedulingType(string $schedulingType): void {
        $this->schedulingType = $schedulingType;
    }

    public function setMeta(array $meta): void {
        $this->meta = $meta;
    }

    public function isActive(): bool {
        return $this->status === 'active';
    }

    public function hasPrerequisites(): bool {
        return !empty($this->prerequisites);
    }

    public function getFormattedPrice(): string {
        return '$' . number_format($this->price, 2);
    }

    public function getDurationInMinutes(): int {
        return $this->duration;
    }

    public function getDurationFormatted(): string {
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        } else {
            return sprintf('%dm', $minutes);
        }
    }

    public function isFixedSchedule(): bool {
        return $this->schedulingType === 'fixed';
    }

    public function isFlexibleSchedule(): bool {
        return $this->schedulingType === 'flexible';
    }
}