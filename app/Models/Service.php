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
        'dripfeed_enabled',
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'dripfeed_enabled' => 'boolean',
        'user_can_cancel' => 'boolean',
        'deny_link_duplicates' => 'boolean',
        'deny_duplicates_days' => 'integer',
        'increment' => 'integer',
        'start_count_parsing_enabled' => 'boolean',
        'auto_complete_enabled' => 'boolean',
        'refill_enabled' => 'boolean',
        'is_active' => 'boolean',
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

}
