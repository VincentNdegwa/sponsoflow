<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'template_id',
        'title',
        'description',
        'total_budget',
        'content_brief',
        'deliverables',
        'status',
        'is_public',
        'posted_at',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'total_budget' => 'decimal:2',
            'content_brief' => 'array',
            'deliverables' => 'array',
            'status' => CampaignStatus::class,
            'is_public' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CampaignTemplate::class, 'template_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(CampaignSlot::class);
    }
}
