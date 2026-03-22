<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'template_id',
        'title',
        'total_budget',
        'content_brief',
        'deliverables',
        'status',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'total_budget' => 'decimal:2',
            'content_brief' => 'array',
            'deliverables' => 'array',
            'status' => CampaignStatus::class,
            'is_public' => 'boolean',
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
}
