<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    // Mode constants
    public const MODE_MANUAL = 'manual';
    public const MODE_PROVIDER = 'provider';
    public const MODE_DEFAULT = 'manual';

    // Service type constants
    public const TYPE_DEFAULT = 'default';

    // Target type constants
    public const TARGET_TYPE_BOT = 'bot';
    public const TARGET_TYPE_CHANNEL = 'channel';
    public const TARGET_TYPE_GROUP = 'group';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'category_id',
        'mode',
        'service_type',
        'target_type',
        'template_key',
        'duration_days',
        'template_snapshot',
        'dripfeed_enabled',
        'speed_limit_enabled',
        'speed_multiplier_fast',
        'speed_multiplier_super_fast',
        'rate_multiplier_fast',
        'rate_multiplier_super_fast',
        'requires_subscription',
        'required_subscription_template_key',
        'user_can_cancel',
        'rate_per_1000',
        'service_cost_per_1000',
        'min_quantity',
        'max_quantity',
        'deny_link_duplicates',
        'deny_duplicates_days',
        'increment',
        'start_count_parsing_enabled',
        'count_type',
        'auto_complete_enabled',
        'refill_enabled',
        'is_active',
        'priority',
        'provider',
        'provider_service_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'dripfeed_enabled' => 'boolean',
        'speed_limit_enabled' => 'boolean',
        'requires_subscription' => 'boolean',
        'user_can_cancel' => 'boolean',
        'deny_link_duplicates' => 'boolean',
        'deny_duplicates_days' => 'integer',
        'increment' => 'integer',
        'start_count_parsing_enabled' => 'boolean',
        'auto_complete_enabled' => 'boolean',
        'refill_enabled' => 'boolean',
        'priority' => 'integer',
        'provider_service_id' => 'integer',
        'is_active' => 'boolean',
        'duration_days' => 'integer',
        'speed_multiplier_fast' => 'decimal:2',
        'speed_multiplier_super_fast' => 'decimal:2',
        'rate_multiplier_fast' => 'decimal:3',
        'rate_multiplier_super_fast' => 'decimal:3',
        'template_snapshot' => 'array',
    ];

    /**
     * Get the category that owns this service.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the clients who favorited this service.
     */
    public function favoritedByClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_service_favorites', 'service_id', 'client_id')
            ->withTimestamps();
    }

    /**
     * Get the client limits for this service.
     */
    public function clientLimits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientServiceLimit::class);
    }

    /**
     * Get the template configuration for this service.
     * Returns template from config or template_snapshot if available.
     *
     * @return array|null
     */
    public function template(): ?array
    {
        if ($this->template_snapshot) {
            return $this->template_snapshot;
        }

        if (!$this->template_key) {
            return null;
        }

        return config("telegram_service_templates.{$this->template_key}");
    }

    /**
     * Get the action from template.
     *
     * @return string|null
     */
    public function action(): ?string
    {
        $template = $this->template();
        return $template['action'] ?? null;
    }

    /**
     * Get the policy key from template.
     *
     * @return string|null
     */
    public function policyKey(): ?string
    {
        $template = $this->template();
        return $template['policy_key'] ?? null;
    }

    /**
     * Determine executor for task execution: local MadelineProto vs remote provider pull.
     * manual => local_mtproto, provider => remote_provider. Default remote_provider.
     *
     * @return string 'local_mtproto'|'remote_provider'
     */
    public function executor(): string
    {
        $mode = (string) ($this->mode ?? self::MODE_PROVIDER);
        return $mode === self::MODE_PROVIDER ? 'local_mtproto' : 'remote_provider';
    }

    /**
     * Get allowed link kinds from template.
     *
     * @return array
     */
    public function allowedLinkKinds(): array
    {
        $template = $this->template();
        return $template['allowed_link_kinds'] ?? [];
    }

    /**
     * Get allowed peer types from template.
     *
     * @return array
     */
    public function allowedPeerTypes(): array
    {
        $template = $this->template();
        return $template['allowed_peer_types'] ?? [];
    }

    /**
     * Check if template requires start param.
     *
     * @return bool
     */
    public function requiresStartParam(): bool
    {
        $template = $this->template();
        return (bool) ($template['requires_start_param'] ?? false);
    }

    /**
     * Get speed multiplier for a given tier.
     *
     * @param string $tier normal|fast|super_fast
     * @return float
     */
    public function getSpeedMultiplier(string $tier): float
    {
        return match($tier) {
            'fast' => (float) ($this->speed_multiplier_fast ?? 1.50),
            'super_fast' => (float) ($this->speed_multiplier_super_fast ?? 2.00),
            default => 1.00,
        };
    }

    public function rateMultiplierForTier(string $tier): float
    {
        return match($tier) {
            'fast' => $this->rate_multiplier_fast,
            'super_fast' => $this->rate_multiplier_super_fast,
            default => $this->rate_per_1000,
        };
    }

    /**
     * Check if speed limit and dripfeed are mutually exclusive.
     *
     * @return bool
     */
    public function hasConflictingOptions(): bool
    {
        return $this->speed_limit_enabled && $this->dripfeed_enabled;
    }
}
