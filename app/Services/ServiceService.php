<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceService implements ServiceServiceInterface
{
    public function __construct(
        private ServiceRepositoryInterface $serviceRepository
    ) {
    }

    /**
     * Get all services with their category.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllServicesWithCategory(array $filters = []): Collection
    {
        return $this->serviceRepository->getAllWithCategory($filters);
    }

    /**
     * Get all services.
     *
     * @return Collection
     */
    public function getAllServices(): Collection
    {
        return $this->serviceRepository->getAll();
    }

    /**
     * Get services by category ID.
     *
     * @param int $categoryId
     * @param bool $activeOnly
     * @return Collection
     */
    public function getServicesByCategoryId(int $categoryId, bool $activeOnly = false): Collection
    {
        return $this->serviceRepository->getByCategoryId($categoryId, $activeOnly);
    }


    /**
     * @param int $serviceId
     * @param int $categoryId
     * @return Service
     */
    public function getServicesByIdAndCategoryId(int $serviceId, int $categoryId): Service
    {
        return $this->serviceRepository->getServicesByIdAndCategoryId($serviceId, $categoryId);
    }


    public function getActiveServicesByCategoryIds(array $categoryIds): Collection
    {
        return $this->serviceRepository->getActiveServicesByCategoryIds($categoryIds);
    }

    /**
     * Find a service by ID.
     *
     * @param int $id
     * @return Service|null
     */
    public function getServiceById(int $id): ?Service
    {
        return $this->serviceRepository->findById($id);
    }

    /**
     * Create a new service.
     *
     * @param array $data
     * @return Service
     */
    public function createService(array $data): Service
    {
        // Auto-generate name if template is selected
        if (isset($data['template_key']) && $data['template_key']) {
            if (empty($data['name']) || ($data['name'] === '')) {
                $data['name'] = $this->generateServiceName($data['template_key'], $data['duration_days'] ?? null);
            }
        }

        // Store template snapshot and auto-set target_type from template
        if (isset($data['template_key']) && $data['template_key']) {
            $template = config("telegram_service_templates.{$data['template_key']}");
            if ($template) {
                $data['template_snapshot'] = $template;
                
                // Auto-set target_type from template if not provided
                if (empty($data['target_type'])) {
                    $data['target_type'] = $this->getTargetTypeFromTemplate($template);
                }
            }
        }

        // Enforce mutual exclusivity of speed_limit_enabled and dripfeed_enabled
        if (!empty($data['speed_limit_enabled']) && !empty($data['dripfeed_enabled'])) {
            // If both are set, prioritize speed_limit_enabled
            $data['dripfeed_enabled'] = false;
        }

        // If speed_limit_enabled is false or not set, force rate multipliers to 1.000
        if (empty($data['speed_limit_enabled'])) {
            $data['rate_multiplier_fast'] = 1.000;
            $data['rate_multiplier_super_fast'] = 1.000;
        }

        return $this->serviceRepository->create($data);
    }

    /**
     * Determine target_type from template's allowed_peer_types.
     *
     * @param array $template
     * @return string|null
     */
    private function getTargetTypeFromTemplate(array $template): ?string
    {
        $peerTypes = $template['allowed_peer_types'] ?? [];
        
        if (in_array('bot', $peerTypes, true)) {
            return 'bot';
        }
        
        if (in_array('channel', $peerTypes, true)) {
            return 'channel';
        }
        
        if (in_array('group', $peerTypes, true) || in_array('supergroup', $peerTypes, true)) {
            return 'group';
        }
        
        return null;
    }

    /**
     * Generate service name from template.
     *
     * @param string $templateKey
     * @param int|null $durationDays
     * @return string
     */
    private function generateServiceName(string $templateKey, ?int $durationDays): string
    {
        $template = config("telegram_service_templates.{$templateKey}");
        if (!$template) {
            return 'Service';
        }

        $label = $template['label'] ?? 'Service';

        // For templates that require duration input, append duration to name
        if (($template['requires_duration_days'] ?? false) && $durationDays) {
            // Remove "(Daily)" from label and append duration
            $cleanLabel = str_replace(' (Daily)', '', $label);
            if (str_contains($cleanLabel, 'Channel Subscribe')) {
                return "Channel Subscribe {$durationDays}d";
            }
            if (str_contains($cleanLabel, 'Group Subscribe')) {
                return "Group Subscribe {$durationDays}d";
            }
            return "{$cleanLabel} {$durationDays}d";
        }

        return $label;
    }

    /**
     * Update a service.
     *
     * @param Service $service
     * @param array $data
     * @return bool
     */
    public function updateService(Service $service, array $data): bool
    {
        // Auto-generate name if template is selected and name is empty
        if (isset($data['template_key']) && $data['template_key']) {
            if (empty($data['name'])) {
                $data['name'] = $this->generateServiceName($data['template_key'], $data['duration_days'] ?? null);
            }
        }

        // Store template snapshot and auto-set target_type from template
        if (isset($data['template_key']) && $data['template_key']) {
            $template = config("telegram_service_templates.{$data['template_key']}");
            if ($template) {
                $data['template_snapshot'] = $template;
                
                // Auto-set target_type from template if not provided
                if (empty($data['target_type'])) {
                    $data['target_type'] = $this->getTargetTypeFromTemplate($template);
                }
            }
        }

        // Enforce mutual exclusivity of speed_limit_enabled and dripfeed_enabled
        if (isset($data['speed_limit_enabled']) && isset($data['dripfeed_enabled'])) {
            if ($data['speed_limit_enabled'] && $data['dripfeed_enabled']) {
                // If both are set, prioritize speed_limit_enabled
                $data['dripfeed_enabled'] = false;
            }
        } elseif (isset($data['speed_limit_enabled']) && $data['speed_limit_enabled']) {
            // If speed_limit is enabled, ensure dripfeed is disabled
            $data['dripfeed_enabled'] = false;
        } elseif (isset($data['dripfeed_enabled']) && $data['dripfeed_enabled']) {
            // If dripfeed is enabled, ensure speed_limit is disabled
            $data['speed_limit_enabled'] = false;
        }

        // If speed_limit_enabled is false, force rate multipliers to 1.000
        if (isset($data['speed_limit_enabled']) && !$data['speed_limit_enabled']) {
            $data['rate_multiplier_fast'] = 1.000;
            $data['rate_multiplier_super_fast'] = 1.000;
        }

        return $this->serviceRepository->update($service, $data);
    }

    /**
     * Delete a service.
     * This performs a soft delete and cleans up related data.
     *
     * @param Service $service
     * @return bool
     */
    public function deleteService(Service $service): bool
    {
        // Detach all client favorites before deleting
        $service->favoritedByClients()->detach();

        // Perform soft delete
        return $this->serviceRepository->delete($service);
    }

    /**
     * Toggle service status (enable/disable).
     *
     * @param Service $service
     * @return bool
     */
    public function toggleServiceStatus(Service $service): bool
    {
        $service->is_active = !$service->is_active;
        return $service->save();
    }

    /**
     * Duplicate a service.
     *
     * @param Service $service
     * @return Service
     */
    public function duplicateService(Service $service): Service
    {
        $data = $service->toArray();

        // Remove fields that shouldn't be copied
        unset(
            $data['id'],
            $data['created_at'],
            $data['updated_at'],
            $data['deleted_at'] // Exclude soft delete timestamp
        );

        // Append " (Copy)" to the service name
        $data['name'] = $data['name'] . ' (Copy)';

        // Ensure the duplicated service is active by default
        $data['is_active'] = true;

        return $this->serviceRepository->create($data);
    }

    /**
     * Restore a soft-deleted service.
     *
     * @param Service $service
     * @return bool
     */
    public function restoreService(Service $service): bool
    {
        return $this->serviceRepository->restore($service);
    }
}

