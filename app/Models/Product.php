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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'custom_attributes' => 'array',
            'is_active' => 'boolean',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWorkspace($query, Workspace $workspace)
    {
        return $query->where('workspace_id', $workspace->id);
    }
}
