<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'category_id',
        'currency',
        'is_active',
        'preview_variant',
        'preview_badge',
        'preview_features',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'preview_variant' => 'integer',
        'preview_features' => 'array',
    ];

    /**
     * Get the category that owns this subscription plan.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the prices for this subscription plan.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class);
    }

    /**
     * Get the services for this subscription plan.
     */
    public function planServices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanService::class);
    }

    /**
     * Get the services through the pivot table.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'subscription_plan_services')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Get the daily price.
     */
    public function getDailyPriceAttribute()
    {
        return $this->prices()->where('billing_cycle', 'daily')->first()?->price;
    }

    /**
     * Get the monthly price.
     */
    public function getMonthlyPriceAttribute()
    {
        return $this->prices()->where('billing_cycle', 'monthly')->first()?->price;
    }
}
