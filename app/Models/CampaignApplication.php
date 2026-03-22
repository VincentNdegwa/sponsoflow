<?php

namespace App\Models;

use App\Enums\CampaignApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'creator_workspace_id',
        'product_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignApplicationStatus::class,
            'notes' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'creator_workspace_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function slot(): HasOne
    {
        return $this->hasOne(CampaignSlot::class, 'application_id');
    }
}
