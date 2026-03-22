<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'category_id',
        'name',
        'deliverable_options',
        'form_schema',
        'is_global',
    ];

    protected function casts(): array
    {
        return [
            'deliverable_options' => 'array',
            'form_schema' => 'array',
            'is_global' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'template_id');
    }
}
