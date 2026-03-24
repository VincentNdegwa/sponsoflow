<?php

namespace App\Services;

use App\Enums\CampaignApplicationStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\CampaignSlot;
use App\Models\CampaignTemplate;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignService
{
    public function createCampaign(
        ?CampaignTemplate $template,
        array $contentBrief,
        array $deliverables,
        ?string $title = null,
        ?string $description = null,
        bool $isPublic = false,
        CampaignStatus $status = CampaignStatus::Draft,
    ): Campaign {
        $brandWorkspace = currentWorkspace();

        if (! $brandWorkspace || ! $brandWorkspace->isBrand()) {
            throw new AuthorizationException('A brand workspace is required to create campaigns.');
        }

        $normalizedDeliverables = $this->normalizeDeliverables($deliverables);

        return DB::transaction(function () use ($brandWorkspace, $template, $contentBrief, $normalizedDeliverables, $title, $description, $isPublic, $status) {
            $shouldPost = $isPublic && in_array($status->value, ['published', 'paused'], true);

            return Campaign::query()->create([
                'workspace_id' => $brandWorkspace->id,
                'template_id' => $template?->id,
                'title' => $title ?: 'New Campaign',
                'description' => $description,
                'total_budget' => $this->calculateTotalBudget($normalizedDeliverables),
                'content_brief' => $contentBrief,
                'deliverables' => $normalizedDeliverables,
                'status' => $status,
                'is_public' => $isPublic,
                'posted_at' => $shouldPost ? now() : null,
            ]);
        });
    }

    public function updateCampaign(
        Campaign $campaign,
        ?CampaignTemplate $template,
        array $contentBrief,
        array $deliverables,
        ?string $title = null,
        ?string $description = null,
        ?bool $isPublic = null,
        ?CampaignStatus $status = null,
    ): Campaign {
        $brandWorkspace = currentWorkspace();

        if (! $brandWorkspace || ! $brandWorkspace->isBrand()) {
            throw new AuthorizationException('A brand account is required to update campaigns.');
        }

        if ((int) $campaign->workspace_id !== (int) $brandWorkspace->id) {
            throw new AuthorizationException('You can only update your own campaigns.');
        }

        $normalizedDeliverables = $this->normalizeDeliverables($deliverables);

        return DB::transaction(function () use ($campaign, $template, $contentBrief, $normalizedDeliverables, $title, $description, $isPublic, $status) {
            $nextStatus = $status ?? $campaign->status;
            $nextIsPublic = $isPublic ?? $campaign->is_public;
            $postedAt = $campaign->posted_at;

            if (! $postedAt && $nextIsPublic && in_array($nextStatus->value, ['published', 'paused'], true)) {
                $postedAt = now();
            }

            $campaign->update([
                'template_id' => $template?->id,
                'title' => $title ?: $campaign->title,
                'description' => $description ?? $campaign->description,
                'total_budget' => $this->calculateTotalBudget($normalizedDeliverables),
                'content_brief' => $contentBrief,
                'deliverables' => $normalizedDeliverables,
                'status' => $nextStatus->value,
                'is_public' => $nextIsPublic,
                'posted_at' => $postedAt,
            ]);

            return $campaign->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $notes
     */
    public function submitApplication(
        Campaign $campaign,
        Workspace $creatorWorkspace,
        Product $product,
        ?array $notes = null,
    ): CampaignApplication {
        if (! $creatorWorkspace->isCreator()) {
            throw new InvalidArgumentException('Only creator workspaces can apply to campaigns.');
        }

        if ((int) $product->workspace_id !== (int) $creatorWorkspace->id) {
            throw new InvalidArgumentException('Creators can only apply using their own products.');
        }

        if (! $campaign->is_public || ! $campaign->status->canAcceptApplications()) {
            throw new InvalidArgumentException('This campaign is not currently accepting applications.');
        }

        return DB::transaction(function () use ($campaign, $creatorWorkspace, $product, $notes) {
            return CampaignApplication::query()->updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'creator_workspace_id' => $creatorWorkspace->id,
                    'product_id' => $product->id,
                ],
                [
                    'status' => CampaignApplicationStatus::Submitted,
                    'notes' => $notes,
                ],
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $deliverables
     * @param  array<string, mixed>|null  $contentBrief
     */
    public function approveApplication(
        CampaignApplication $application,
        ?array $deliverables = null,
        ?array $contentBrief = null,
    ): CampaignSlot {
        $brandWorkspace = currentWorkspace();

        if (! $brandWorkspace || ! $brandWorkspace->isBrand()) {
            throw new AuthorizationException('A brand workspace is required to approve applications.');
        }

        $campaign = $application->campaign()->firstOrFail();

        if ((int) $campaign->workspace_id !== (int) $brandWorkspace->id) {
            throw new AuthorizationException('You can only approve applications for your own campaigns.');
        }

        if ($application->slot()->exists()) {
            throw new InvalidArgumentException('This application has already been approved into a slot.');
        }

        $slotDeliverables = $deliverables ?? (array) ($campaign->deliverables ?? []);
        $normalizedDeliverables = $this->normalizeDeliverables($slotDeliverables);
        $slotContentBrief = $contentBrief ?? (array) ($campaign->content_brief ?? []);

        return DB::transaction(function () use ($application, $normalizedDeliverables, $slotContentBrief, $campaign) {
            $application->update([
                'status' => CampaignApplicationStatus::Approved,
            ]);

            return CampaignSlot::query()->create([
                'campaign_id' => $campaign->id,
                'application_id' => $application->id,
                'creator_workspace_id' => $application->creator_workspace_id,
                'product_id' => $application->product_id,
                'status' => CampaignSlotStatus::Pending,
                'deliverables' => $normalizedDeliverables,
                'content_brief' => $slotContentBrief,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $contentBrief
     * @param  array<int, array<string, mixed>>  $deliverables
     */
    public function createInquiryCampaignSlot(
        Workspace $creatorWorkspace,
        Product $product,
        array $contentBrief,
        array $deliverables,
        ?string $title = null,
    ): CampaignSlot {
        if (! $creatorWorkspace->isCreator()) {
            throw new InvalidArgumentException('Inquiry slots must target a creator workspace.');
        }

        if ((int) $product->workspace_id !== (int) $creatorWorkspace->id) {
            throw new InvalidArgumentException('Inquiry slots must use a product owned by the creator workspace.');
        }

        return DB::transaction(function () use ($creatorWorkspace, $product, $contentBrief, $deliverables, $title) {
            $campaign = $this->createCampaign(
                template: null,
                contentBrief: $contentBrief,
                deliverables: $deliverables,
                title: $title,
                isPublic: false,
                status: CampaignStatus::Draft,
            );

            $normalizedDeliverables = $this->normalizeDeliverables($deliverables);
            $campaignBudget = $this->calculateTotalBudget($normalizedDeliverables);

            return CampaignSlot::query()->create([
                'campaign_id' => $campaign->id,
                'application_id' => null,
                'creator_workspace_id' => $creatorWorkspace->id,
                'product_id' => $product->id,
                'status' => CampaignSlotStatus::Pending,
                'deliverables' => $normalizedDeliverables,
                'content_brief' => $contentBrief,
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

    public function visibleForWorkspace(Workspace $workspace): Collection
    {
        return Campaign::query()
            ->where(function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->orderByRaw('workspace_id is null desc')
            ->orderBy('title')
            ->get();
    }
}
