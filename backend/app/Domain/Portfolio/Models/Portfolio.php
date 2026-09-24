<?php

namespace App\Domain\Portfolio\Models;

use App\Domain\Properties\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    // Public plan information comes from PlanService, not historical billing fields.
    protected $hidden = ['plan', 'storage_limit_bytes', 'subscription_status', 'trial_ends_at', 'plan_changed_at', 'pending_plan', 'pending_billing_period', 'pending_plan_effective_at', 'billing_customer_id', 'billing_subscription_id', 'billing_schedule_id'];

    protected $fillable = ['name', 'currency', 'country_code', 'plan', 'storage_limit_bytes', 'subscription_status', 'trial_ends_at', 'plan_changed_at', 'pending_plan', 'pending_billing_period', 'pending_plan_effective_at', 'billing_customer_id', 'billing_subscription_id', 'billing_schedule_id'];

    protected function casts(): array
    {
        return [
            'plan_changed_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'pending_plan_effective_at' => 'datetime',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'portfolio_members')->withPivot('role')->withTimestamps();
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
