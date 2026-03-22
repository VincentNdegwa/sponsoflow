<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'type',
        'base_price',
        'duration_minutes',
        'custom_attributes',
        'default_deliverables',
        'is_active',
        'is_public',
        'featured_order',
        'sold_count',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'custom_attributes' => 'array',
            'default_deliverables' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProductRequirement::class)->orderBy('sort_order');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function availableSlots(): HasMany
    {
        return $this->hasMany(Slot::class)->where('status', 'available');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function publicAvailableSlots(): HasMany
    {
        return $this->availableSlots()
            ->whereDate('slot_date', '>=', now())
            ->whereNull('reserved_at')
            ->orWhere('reserved_at', '<', now()->subMinutes(15));
    }

    public function incrementSoldCount(): void
    {
        $this->increment('sold_count');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true)->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->orderBy('featured_order')->orderBy('name');
    }

    public function scopeForWorkspace($query, Workspace $workspace)
    {
        return $query->where('workspace_id', $workspace->id);
    }
}
