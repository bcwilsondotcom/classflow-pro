<?php
declare(strict_types=1);

namespace ClassFlowPro\Services;

use ClassFlowPro\Models\Entities\ClassEntity;
use ClassFlowPro\Models\Repositories\ClassRepository;
use ClassFlowPro\Models\Repositories\ScheduleRepository;

class ClassService {
    private ClassRepository $classRepository;
    private ScheduleRepository $scheduleRepository;
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->classRepository = new ClassRepository();
        $this->scheduleRepository = new ScheduleRepository();
    }

    public function createClass(array $data): ClassEntity {
        // Validate required fields
        $this->validateClassData($data);
        
        // Generate unique slug
        $slug = $this->classRepository->generateUniqueSlug($data['name']);
        
        // Create class entity
        $class = new ClassEntity(
            $data['name'],
            $slug,
            $data['duration'] ?? 60,
            $data['capacity'] ?? 10,
            $data['price'] ?? 0.0,
            $data['status'] ?? 'active'
        );
        
        // Set optional fields
        if (isset($data['description'])) {
            $class->setDescription($data['description']);
        }
        
        if (isset($data['category_id'])) {
            $class->setCategoryId((int) $data['category_id']);
        }
        
        if (isset($data['featured_image_id'])) {
            $class->setFeaturedImageId((int) $data['featured_image_id']);
        }
        
        if (isset($data['gallery_ids'])) {
            $class->setGalleryIds($data['gallery_ids']);
        }
        
        if (isset($data['prerequisites'])) {
            $class->setPrerequisites($data['prerequisites']);
        }
        
        if (isset($data['skill_level'])) {
            $class->setSkillLevel($data['skill_level']);
        }
        
        if (isset($data['scheduling_type'])) {
            $class->setSchedulingType($data['scheduling_type']);
        }
        
        if (isset($data['meta'])) {
            $class->setMeta($data['meta']);
        }
        
        // Save to database
        $class = $this->classRepository->save($class);
        
        // Trigger action
        do_action('classflow_pro_class_created', $class);
        
        return $class;
    }

    public function updateClass(int $id, array $data): ClassEntity {
        $class = $this->classRepository->find($id);
        if (!$class) {
            throw new \RuntimeException(__('Class not found.', 'classflow-pro'));
        }
        
        // Update fields
        if (isset($data['name'])) {
            $class->setName($data['name']);
            
            // Update slug if name changed
            if (isset($data['update_slug']) && $data['update_slug']) {
                $slug = $this->classRepository->generateUniqueSlug($data['name'], $id);
                $class->setSlug($slug);
            }
        }
        
        if (isset($data['description'])) {
            $class->setDescription($data['description']);
        }
        
        if (isset($data['category_id'])) {
            $class->setCategoryId($data['category_id'] ? (int) $data['category_id'] : null);
        }
        
        if (isset($data['duration'])) {
            $class->setDuration((int) $data['duration']);
        }
        
        if (isset($data['capacity'])) {
            $class->setCapacity((int) $data['capacity']);
        }
        
        if (isset($data['price'])) {
            $class->setPrice((float) $data['price']);
        }
        
        if (isset($data['status'])) {
            $class->setStatus($data['status']);
        }
        
        if (isset($data['featured_image_id'])) {
            $class->setFeaturedImageId($data['featured_image_id'] ? (int) $data['featured_image_id'] : null);
        }
        
        if (isset($data['gallery_ids'])) {
            $class->setGalleryIds($data['gallery_ids']);
        }
        
        if (isset($data['prerequisites'])) {
            $class->setPrerequisites($data['prerequisites']);
        }
        
        if (isset($data['skill_level'])) {
            $class->setSkillLevel($data['skill_level']);
        }
        
        if (isset($data['scheduling_type'])) {
            $class->setSchedulingType($data['scheduling_type']);
        }
        
        if (isset($data['meta'])) {
            $class->setMeta($data['meta']);
        }
        
        // Save changes
        $class = $this->classRepository->save($class);
        
        // Trigger action
        do_action('classflow_pro_class_updated', $class);
        
        return $class;
    }

    public function deleteClass(int $id): bool {
        $class = $this->classRepository->find($id);
        if (!$class) {
            throw new \RuntimeException(__('Class not found.', 'classflow-pro'));
        }
        
        // Check if class has future schedules
        $futureSchedules = $this->scheduleRepository->findByClass($id, true);
        if (!empty($futureSchedules)) {
            throw new \RuntimeException(__('Cannot delete class with future schedules.', 'classflow-pro'));
        }
        
        // Trigger action before deletion
        do_action('classflow_pro_before_class_delete', $class);
        
        // Delete the class
        $result = $this->classRepository->delete($id);
        
        if ($result) {
            // Trigger action after deletion
            do_action('classflow_pro_class_deleted', $id);
        }
        
        return $result;
    }

    public function getClass(int $id): ?ClassEntity {
        return $this->classRepository->find($id);
    }

    public function getClassBySlug(string $slug): ?ClassEntity {
        return $this->classRepository->findBySlug($slug);
    }

    public function getClasses(array $filters = [], string $orderBy = 'name ASC', int $limit = 0): array {
        return $this->classRepository->findAll($filters, $orderBy, $limit);
    }

    public function getClassesByCategory(int $categoryId, bool $activeOnly = true): array {
        return $this->classRepository->findByCategory($categoryId, $activeOnly);
    }

    public function searchClasses(string $keyword): array {
        return $this->classRepository->search($keyword);
    }

    public function getUpcomingClasses(int $limit = 10): array {
        return $this->classRepository->getUpcomingClasses($limit);
    }

    public function getPopularClasses(int $limit = 10): array {
        return $this->classRepository->getPopularClasses($limit);
    }

    public function duplicateClass(int $id, string $newName): ClassEntity {
        $originalClass = $this->classRepository->find($id);
        if (!$originalClass) {
            throw new \RuntimeException(__('Class not found.', 'classflow-pro'));
        }
        
        // Create new class data
        $data = $originalClass->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);
        $data['name'] = $newName;
        $data['status'] = 'draft'; // Set as draft by default
        
        // Create the duplicate
        return $this->createClass($data);
    }

    public function activateClass(int $id): bool {
        return $this->classRepository->updateStatus($id, 'active');
    }

    public function deactivateClass(int $id): bool {
        return $this->classRepository->updateStatus($id, 'inactive');
    }

    public function getClassCapacity(int $id): int {
        $class = $this->classRepository->find($id);
        return $class ? $class->getCapacity() : 0;
    }

    public function getClassPrice(int $id): float {
        $class = $this->classRepository->find($id);
        return $class ? $class->getPrice() : 0.0;
    }

    public function hasPrerequisites(int $id): bool {
        $class = $this->classRepository->find($id);
        return $class ? $class->hasPrerequisites() : false;
    }

    public function checkPrerequisites(int $classId, int $studentId): bool {
        $class = $this->classRepository->find($classId);
        if (!$class || !$class->hasPrerequisites()) {
            return true; // No prerequisites or class not found
        }
        
        $prerequisites = $class->getPrerequisites();
        
        // Check if student has completed all prerequisite classes
        foreach ($prerequisites as $prerequisiteClassId) {
            if (!$this->hasStudentCompletedClass($studentId, $prerequisiteClassId)) {
                return false;
            }
        }
        
        return true;
    }

    private function hasStudentCompletedClass(int $studentId, int $classId): bool {
        // This would check booking history
        // For now, return true as placeholder
        return true;
    }

    private function validateClassData(array $data): void {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = __('Class name is required.', 'classflow-pro');
        }
        
        if (isset($data['duration']) && $data['duration'] < 1) {
            $errors[] = __('Duration must be at least 1 minute.', 'classflow-pro');
        }
        
        if (isset($data['capacity']) && $data['capacity'] < 1) {
            $errors[] = __('Capacity must be at least 1.', 'classflow-pro');
        }
        
        if (isset($data['price']) && $data['price'] < 0) {
            $errors[] = __('Price cannot be negative.', 'classflow-pro');
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    public function getClassesWithScheduleCount(array $filters = []): array {
        $classes = $this->classRepository->findAll($filters);
        
        foreach ($classes as $class) {
            $schedules = $this->scheduleRepository->findByClass($class->getId(), true);
            $class->setMetaValue('upcoming_schedules_count', count($schedules));
        }
        
        return $classes;
    }

    public function exportClasses(array $classIds = []): array {
        if (empty($classIds)) {
            $classes = $this->classRepository->findAll();
        } else {
            $classes = array_filter(
                array_map([$this->classRepository, 'find'], $classIds)
            );
        }
        
        $exportData = [];
        foreach ($classes as $class) {
            $exportData[] = [
                'name' => $class->getName(),
                'description' => $class->getDescription(),
                'duration' => $class->getDuration(),
                'capacity' => $class->getCapacity(),
                'price' => $class->getPrice(),
                'skill_level' => $class->getSkillLevel(),
                'status' => $class->getStatus(),
            ];
        }
        
        return $exportData;
    }

    public function importClasses(array $data): array {
        $imported = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($data as $index => $classData) {
            try {
                $this->createClass($classData);
                $imported++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = sprintf(
                    __('Row %d: %s', 'classflow-pro'),
                    $index + 1,
                    $e->getMessage()
                );
            }
        }
        
        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}