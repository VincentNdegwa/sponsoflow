<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function createCampaign(
        ?CampaignTemplate $template,
        array $contentBrief,
        array $deliverables,
        ?string $title = null,
        bool $isPublic = false,
        CampaignStatus $status = CampaignStatus::Pending,
    ): Campaign {
        $brandWorkspace = currentWorkspace();

        if (! $brandWorkspace || ! $brandWorkspace->isBrand()) {
            throw new AuthorizationException('A brand workspace is required to create campaigns.');
        }

        $normalizedDeliverables = $this->normalizeDeliverables($deliverables);

        return DB::transaction(function () use ($brandWorkspace, $template, $contentBrief, $normalizedDeliverables, $title, $isPublic, $status) {
            return Campaign::query()->create([
                'workspace_id' => $brandWorkspace->id,
                'template_id' => $template?->id,
                'title' => $title ?: 'New Campaign',
                'total_budget' => $this->calculateTotalBudget($normalizedDeliverables),
                'content_brief' => $contentBrief,
                'deliverables' => $normalizedDeliverables,
                'status' => $status,
                'is_public' => $isPublic,
            ]);
        });
    }

    public function normalizeDeliverables(array $deliverables): array
    {
        $normalized = [];

        foreach ($deliverables as $index => $row) {
            $qty = max(0, (int) data_get($row, 'qty', 0));
            $unitPrice = round((float) data_get($row, 'unit_price', 0), 2);
            $subtotal = round($qty * $unitPrice, 2);

            $normalized[] = [
                'id' => (string) data_get($row, 'id', 'row_'.($index + 1)),
                'deliverable_option_id' => data_get($row, 'deliverable_option_id'),
                'type_slug' => (string) data_get($row, 'type_slug', 'custom'),
                'label' => (string) data_get($row, 'label', 'Deliverable'),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'fields' => (array) data_get($row, 'fields', []),
                'status' => (string) data_get($row, 'status', 'pending'),
                'proof_url' => data_get($row, 'proof_url'),
            ];
        }

        return $normalized;
    }

    public function calculateTotalBudget(array $deliverables): float
    {
        return round(collect($deliverables)->sum(fn (array $row) => (float) data_get($row, 'subtotal', 0)), 2);
    }
}
