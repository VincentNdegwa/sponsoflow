<?php

namespace App\Models;

use App\Enums\CampaignSlotStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'application_id',
        'creator_workspace_id',
        'product_id',
        'status',
        'deliverables',
        'content_brief',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignSlotStatus::class,
            'deliverables' => 'array',
            'content_brief' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CampaignApplication::class, 'application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'creator_workspace_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'campaign_slot_id');
    }
}
