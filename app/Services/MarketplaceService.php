<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\CampaignApplicationStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\CampaignStatus;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Product;
use App\Models\Workspace;
use App\Support\CampaignBookingPayloadFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarketplaceService
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    public function discoverCampaigns(Workspace $workspace, string $search = ''): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->with(['workspace.owner', 'template.category'])
            ->withCount([
                'applications as submitted_applications_count' => fn ($applicationQuery) => $applicationQuery
                    ->where('status', CampaignApplicationStatus::Submitted->value),
                'slots as active_slots_count' => fn ($slotQuery) => $slotQuery
                    ->where('status', CampaignSlotStatus::Active->value),
            ])
            ->where('is_public', true)
            ->whereIn('status', [
                CampaignStatus::Published->value,
                CampaignStatus::Paused->value,
            ])
            ->latest();

        if ($workspace->isBrand()) {
            $query->where('workspace_id', '!=', $workspace->id);
        }

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';

            $query->where(function ($builder) use ($searchTerm): void {
                $builder->where('title', 'like', $searchTerm)
                    ->orWhereHas('workspace', fn ($workspaceQuery) => $workspaceQuery->where('name', 'like', $searchTerm));
            });
        }

        if ($workspace->isCreator()) {
            $query->withCount([
                'applications as my_applications_count' => fn ($applicationQuery) => $applicationQuery
                    ->where('creator_workspace_id', $workspace->id),
            ]);
        }

        return $query->paginate(12);
    }

    public function creatorProducts(Workspace $workspace): \Illuminate\Database\Eloquent\Collection
    {
        if (! $workspace->isCreator()) {
            return collect();
        }

        return Product::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();
    }

    public function submitCreatorApplication(
        Campaign $campaign,
        Workspace $creatorWorkspace,
        Product $product,
        ?string $pitch = null,
    ): CampaignApplication {
        if (! $creatorWorkspace->isCreator()) {
            throw new AuthorizationException('Only creator workspaces can apply in the marketplace.');
        }

        if ((int) $campaign->workspace_id === (int) $creatorWorkspace->id) {
            throw new InvalidArgumentException('You cannot apply to your own campaign.');
        }

        $notes = [
            'pitch' => $pitch,
            'source' => 'marketplace',
            'submitted_at' => now()->toIso8601String(),
        ];

        return $this->campaignService->submitApplication(
            campaign: $campaign,
            creatorWorkspace: $creatorWorkspace,
            product: $product,
            notes: $notes,
        );
    }

    public function rejectApplication(CampaignApplication $application, ?string $reason = null): CampaignApplication
    {
        $brandWorkspace = currentWorkspace();

        if (! $brandWorkspace || ! $brandWorkspace->isBrand()) {
            throw new AuthorizationException('Only brand workspaces can reject applications.');
        }

        $campaign = $application->campaign()->firstOrFail();

        if ((int) $campaign->workspace_id !== (int) $brandWorkspace->id) {
            throw new AuthorizationException('You can only reject applications for your own campaigns.');
        }

        if ($application->status !== CampaignApplicationStatus::Submitted) {
            throw new InvalidArgumentException('Only submitted applications can be rejected.');
        }

        $notes = is_array($application->notes) ? $application->notes : [];
        data_set($notes, 'brand_review.reason', $reason);
        data_set($notes, 'brand_review.reviewed_at', now()->toIso8601String());

        $application->update([
            'status' => CampaignApplicationStatus::Rejected,
            'notes' => $notes,
        ]);

        return $application->refresh();
    }

    public function approveApplicationAndCreateBooking(CampaignApplication $application): Booking
    {
        $brandWorkspace = currentWorkspace();

        if (! $brandWorkspace || ! $brandWorkspace->isBrand()) {
            throw new AuthorizationException('Only brand workspaces can approve applications.');
        }

        $campaign = $application->campaign()->firstOrFail();

        if ((int) $campaign->workspace_id !== (int) $brandWorkspace->id) {
            throw new AuthorizationException('You can only approve applications for your own campaigns.');
        }

        if ($application->status !== CampaignApplicationStatus::Submitted && ! $application->slot()->exists()) {
            throw new InvalidArgumentException('Only submitted applications can be approved.');
        }

        return DB::transaction(function () use ($application, $campaign, $brandWorkspace): Booking {
            $slot = $application->slot()->first();

            if (! $slot) {
                $slot = $this->campaignService->approveApplication($application);
            }

            $existingBooking = Booking::query()
                ->where('campaign_slot_id', $slot->id)
                ->first();

            if ($existingBooking) {
                return $existingBooking;
            }

            $creatorWorkspace = $slot->creator()->firstOrFail();
            $creator = $creatorWorkspace->owner()->firstOrFail();

            $campaignDetails = CampaignBookingPayloadFormatter::fromCampaign($campaign);
            $campaignDeliverables = CampaignBookingPayloadFormatter::normalizeDeliverables($slot->deliverables ?: $campaign->deliverables);

            return Booking::query()->create([
                'slot_id' => null,
                'campaign_slot_id' => $slot->id,
                'product_id' => $slot->product_id,
                'creator_id' => $creator->id,
                'workspace_id' => $creatorWorkspace->id,
                'brand_user_id' => $brandWorkspace->owner_id,
                'brand_workspace_id' => $brandWorkspace->id,
                'type' => BookingType::MARKETPLACE_APPLICATION,
                'requirement_data' => [
                    'marketplace_application' => [
                        'campaign_id' => $campaign->id,
                        'campaign_application_id' => $application->id,
                        'campaign_slot_id' => $slot->id,
                    ],
                ],
                'campaign_details' => $campaignDetails,
                'campaign_deliverables' => $campaignDeliverables,
                'amount_paid' => (float) $campaign->total_budget,
                'currency' => $brandWorkspace->currency ?? 'USD',
                'status' => BookingStatus::PENDING,
                'notes' => 'Marketplace application approved by brand. Awaiting creator confirmation.',
            ]);
        });
    }
}
