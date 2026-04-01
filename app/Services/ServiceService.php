<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceService implements ServiceServiceInterface
{
    public function __construct(
        private ServiceRepositoryInterface $serviceRepository
    ) {}

    /**
     * Get all services with their category.
     */
    public function getAllServicesWithCategory(array $filters = []): Collection
    {
        return $this->serviceRepository->getAllWithCategory($filters);
    }

    /**
     * Get all services.
     */
    public function getAllServices(): Collection
    {
        return $this->serviceRepository->getAll();
    }

    /**
     * Get services by category ID.
     */
    public function getServicesByCategoryId(int $categoryId, bool $activeOnly = false): Collection
    {
        return $this->serviceRepository->getByCategoryId($categoryId, $activeOnly);
    }

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
     */
    public function getServiceById(int $id): ?Service
    {
        return $this->serviceRepository->findById($id);
    }

    /**
     * Create a new service.
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
            $template = config("telegram_service_templates.{$data['template_key']}")
                ?? config("youtube_service_templates.{$data['template_key']}")
                ?? config("app_service_templates.{$data['template_key']}");
            if ($template) {
                $data['template_snapshot'] = $template;
                if (empty($data['target_type'])) {
                    $data['target_type'] = $this->getTargetTypeFromTemplate($template)
                        ?? (str_starts_with($data['template_key'], 'yt_') ? 'youtube' : null)
                        ?? (str_starts_with($data['template_key'], 'app_') ? 'app' : null);
                }
            }
        }

        // Enforce mutual exclusivity of speed_limit_enabled and dripfeed_enabled
        if (! empty($data['speed_limit_enabled']) && ! empty($data['dripfeed_enabled'])) {
            // If both are set, prioritize speed_limit_enabled
            $data['dripfeed_enabled'] = false;
        }

        if (! empty($data['speed_limit_enabled']) && empty($data['speed_limit_tier_mode'])) {
            $data['speed_limit_tier_mode'] = 'fast';
        }

        // Price does not vary by speed tier; DB columns kept for schema compatibility
        $data['rate_multiplier_fast'] = 1.000;
        $data['rate_multiplier_super_fast'] = 1.000;

        // Single tier: neutralize unused speed multiplier column (NOT NULL)
        $tierMode = $data['speed_limit_tier_mode'] ?? 'fast';
        if (! empty($data['speed_limit_enabled'])) {
            if ($tierMode === 'super_fast') {
                $data['speed_multiplier_fast'] = 1.00;
            } else {
                $data['speed_multiplier_super_fast'] = 2.00;
            }
        }

        return $this->serviceRepository->create($data);
    }

    /**
     * Determine target_type from template's allowed_peer_types.
     */
    private function getTargetTypeFromTemplate(array $template): ?string
    {
        $peerTypes = $template['allowed_peer_types'] ?? [];
        if (empty($peerTypes)) {
            return null;
        }
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
     */
    private function generateServiceName(string $templateKey, ?int $durationDays): string
    {
        $template = config("telegram_service_templates.{$templateKey}")
            ?? config("youtube_service_templates.{$templateKey}")
            ?? config("app_service_templates.{$templateKey}");
        if (! $template) {
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
            $template = config("telegram_service_templates.{$data['template_key']}")
                ?? config("youtube_service_templates.{$data['template_key']}")
                ?? config("app_service_templates.{$data['template_key']}");
            if ($template) {
                $data['template_snapshot'] = $template;
                if (empty($data['target_type'])) {
                    $data['target_type'] = $this->getTargetTypeFromTemplate($template)
                        ?? (str_starts_with($data['template_key'], 'yt_') ? 'youtube' : null)
                        ?? (str_starts_with($data['template_key'], 'app_') ? 'app' : null);
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

        $data['rate_multiplier_fast'] = 1.000;
        $data['rate_multiplier_super_fast'] = 1.000;

        $tierMode = $data['speed_limit_tier_mode'] ?? $service->speed_limit_tier_mode ?? 'fast';
        if ($tierMode === 'both') {
            $tierMode = 'fast';
            $data['speed_limit_tier_mode'] = 'fast';
        }
        $speedOn = array_key_exists('speed_limit_enabled', $data)
            ? (bool) $data['speed_limit_enabled']
            : (bool) $service->speed_limit_enabled;
        if ($speedOn) {
            if ($tierMode === 'super_fast') {
                $data['speed_multiplier_fast'] = 1.00;
            } else {
                $data['speed_multiplier_super_fast'] = 2.00;
            }
        }

        return $this->serviceRepository->update($service, $data);
    }

    /**
     * Delete a service.
     * This performs a soft delete and cleans up related data.
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
     */
    public function toggleServiceStatus(Service $service): bool
    {
        $service->is_active = ! $service->is_active;

        return $service->save();
    }

    /**
     * Duplicate a service.
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
        $data['name'] = $data['name'].' (Copy)';

        // Ensure the duplicated service is active by default
        $data['is_active'] = true;

        return $this->serviceRepository->create($data);
    }

    /**
     * Restore a soft-deleted service.
     */
    public function restoreService(Service $service): bool
    {
        return $this->serviceRepository->restore($service);
    }
}
