<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\CampaignStatus;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\CampaignSlot;
use App\Support\CampaignBookingPayloadFormatter;
use Illuminate\Support\Facades\DB;

class BookingShadowCampaignService
{
    public function provisionForInquiry(Booking $booking): ?CampaignSlot
    {
        if (! $booking->isInquiry()) {
            return null;
        }

        if (! $booking->brand_workspace_id || ! $booking->workspace_id || ! $booking->product_id) {
            return null;
        }

        $requirementData = is_array($booking->requirement_data) ? $booking->requirement_data : [];

        $campaignDetails = $this->resolveCampaignDetails($booking, $requirementData);
        $budget = $this->resolveBudget($booking, $requirementData, $campaignDetails);
        $shadowCampaign = $this->buildShadowCampaign($booking, $budget, $requirementData, $campaignDetails);

        return $this->upsertShadowCampaignAndSlot(
            booking: $booking,
            requirementData: $requirementData,
            campaignDetails: $campaignDetails,
            shadowCampaign: $shadowCampaign,
            budget: $budget,
            campaignStatus: CampaignStatus::Draft,
            slotStatus: CampaignSlotStatus::Pending,
        );
    }

    public function activateForPaidInquiry(Booking $booking): ?CampaignSlot
    {
        if (! $booking->isInquiry() || $booking->status !== BookingStatus::CONFIRMED) {
            return null;
        }

        if (! $booking->brand_workspace_id || ! $booking->workspace_id || ! $booking->product_id) {
            return null;
        }

        $requirementData = is_array($booking->requirement_data) ? $booking->requirement_data : [];
        $campaignDetails = $this->resolveCampaignDetails($booking, $requirementData);
        $budget = $this->resolveBudget($booking, $requirementData, $campaignDetails);
        $shadowCampaign = $this->buildShadowCampaign($booking, $budget, $requirementData, $campaignDetails);

        return $this->upsertShadowCampaignAndSlot(
            booking: $booking,
            requirementData: $requirementData,
            campaignDetails: $campaignDetails,
            shadowCampaign: $shadowCampaign,
            budget: $budget,
            campaignStatus: CampaignStatus::Published,
            slotStatus: CampaignSlotStatus::Active,
        );
    }

    // Backward compatibility for existing calls.
    public function provisionForPaidInquiry(Booking $booking): ?CampaignSlot
    {
        return $this->activateForPaidInquiry($booking);
    }

    /**
     * @param  array<string, mixed>  $requirementData
     */
    private function resolveBudget(Booking $booking, array $requirementData, array $campaignDetails): float
    {
        return (float) data_get(
            $campaignDetails,
            'meta.total_budget',
            (float) data_get($requirementData, 'budget', (float) $booking->amount_paid),
        );
    }

    /**
     * @param  array<string, mixed>  $requirementData
     * @return array<string, mixed>
     */
    private function resolveCampaignDetails(Booking $booking, array $requirementData): array
    {
        $campaignDetails = is_array($booking->campaign_details) ? $booking->campaign_details : [];

        if (! empty($campaignDetails)) {
            return $campaignDetails;
        }

        $legacyShadowCampaign = (array) data_get($requirementData, 'shadow_campaign', []);

        return $legacyShadowCampaign;
    }

    /**
     * @param  array<string, mixed>  $requirementData
     * @param  array<string, mixed>  $campaignDetails
     * @return array<string, mixed>
     */
    private function buildShadowCampaign(Booking $booking, float $budget, array $requirementData, array $campaignDetails): array
    {
        if (! empty($campaignDetails)) {
            data_set($campaignDetails, 'meta.total_budget', $budget);

            if (blank(data_get($campaignDetails, 'meta.campaign_name'))) {
                data_set($campaignDetails, 'meta.campaign_name', 'New Campaign');
            }

            return $campaignDetails;
        }

        $selectedCampaignId = (int) data_get($requirementData, 'shadow_campaign.selected_campaign_id', 0);

        if ($selectedCampaignId > 0 && $booking->brand_workspace_id) {
            $selectedCampaign = Campaign::query()
                ->whereKey($selectedCampaignId)
                ->where('workspace_id', $booking->brand_workspace_id)
                ->first();

            if ($selectedCampaign) {
                return CampaignBookingPayloadFormatter::fromCampaign($selectedCampaign, $budget);
            }
        }

        return CampaignBookingPayloadFormatter::fromInquiryInput(
            creatorName: (string) ($booking->creator?->name ?? 'creator'),
            budget: $budget,
            input: $requirementData,
        );
    }

    private function upsertShadowCampaignAndSlot(
        Booking $booking,
        array $requirementData,
        array $campaignDetails,
        array $shadowCampaign,
        float $budget,
        CampaignStatus $campaignStatus,
        CampaignSlotStatus $slotStatus,
    ): ?CampaignSlot {
        return DB::transaction(function () use ($booking, $requirementData, $campaignDetails, $shadowCampaign, $budget, $campaignStatus, $slotStatus): ?CampaignSlot {
            $selectedCampaignId = (int) data_get($campaignDetails, 'selected_campaign_id', data_get($requirementData, 'shadow_campaign.selected_campaign_id', 0));
            $campaignId = (int) data_get($campaignDetails, 'provisioned_campaign_id', data_get($requirementData, 'shadow_campaign.provisioned_campaign_id', 0));
            $slotId = (int) data_get($campaignDetails, 'provisioned_slot_id', data_get($requirementData, 'shadow_campaign.provisioned_slot_id', 0));
            $campaign = null;

            if ($selectedCampaignId > 0) {
                $campaign = Campaign::query()
                    ->whereKey($selectedCampaignId)
                    ->where('workspace_id', $booking->brand_workspace_id)
                    ->first();
            }

            if (! $campaign && $campaignId > 0) {
                $campaign = Campaign::query()
                    ->whereKey($campaignId)
                    ->where('workspace_id', $booking->brand_workspace_id)
                    ->first();
            }

            $usesSelectedCampaign = $campaign !== null && $selectedCampaignId > 0;

            if (! $campaign) {
                $campaign = Campaign::query()->create([
                    'workspace_id' => $booking->brand_workspace_id,
                    'template_id' => null,
                    'title' => (string) data_get($shadowCampaign, 'meta.campaign_name', 'New Campaign'),
                    'total_budget' => $budget,
                    'content_brief' => [
                        'source' => 'booking_inquiry',
                        'booking_id' => $booking->id,
                        'campaign_details' => $shadowCampaign,
                    ],
                    'deliverables' => [],
                    'status' => $campaignStatus,
                    'is_public' => false,
                ]);
            } elseif (! $usesSelectedCampaign) {
                $campaign->update([
                    'title' => (string) data_get($shadowCampaign, 'meta.campaign_name', $campaign->title),
                    'total_budget' => $budget,
                    'content_brief' => [
                        'source' => 'booking_inquiry',
                        'booking_id' => $booking->id,
                        'campaign_details' => $shadowCampaign,
                    ],
                    'status' => $campaignStatus,
                ]);
            }

            $bookingDeliverables = CampaignBookingPayloadFormatter::normalizeDeliverables($booking->campaign_deliverables);

            $slot = $slotId > 0 ? CampaignSlot::query()->find($slotId) : null;

            if (! $slot) {
                $slot = CampaignSlot::query()->create([
                    'campaign_id' => $campaign->id,
                    'application_id' => null,
                    'creator_workspace_id' => $booking->workspace_id,
                    'product_id' => $booking->product_id,
                    'status' => $slotStatus,
                    'deliverables' => ! empty($bookingDeliverables)
                        ? $bookingDeliverables
                        : CampaignBookingPayloadFormatter::normalizeDeliverables($campaign->deliverables),
                    'content_brief' => [
                        'source' => 'booking_inquiry',
                        'booking_id' => $booking->id,
                        'campaign_details' => $shadowCampaign,
                    ],
                ]);
            } else {
                $slot->update([
                    'campaign_id' => $campaign->id,
                    'creator_workspace_id' => $booking->workspace_id,
                    'product_id' => $booking->product_id,
                    'status' => $slotStatus,
                    'deliverables' => ! empty($bookingDeliverables)
                        ? $bookingDeliverables
                        : CampaignBookingPayloadFormatter::normalizeDeliverables($campaign->deliverables),
                    'content_brief' => [
                        'source' => 'booking_inquiry',
                        'booking_id' => $booking->id,
                        'campaign_details' => $shadowCampaign,
                    ],
                ]);
            }

            $updatedCampaignDetails = array_merge($campaignDetails, $shadowCampaign);
            data_set($updatedCampaignDetails, 'provisioned_campaign_id', $campaign->id);
            data_set($updatedCampaignDetails, 'provisioned_slot_id', $slot->id);
            data_set($updatedCampaignDetails, 'selected_campaign_id', $selectedCampaignId > 0 ? $selectedCampaignId : null);

            $booking->update([
                'campaign_details' => $updatedCampaignDetails,
                'campaign_deliverables' => ! empty($bookingDeliverables)
                    ? $bookingDeliverables
                    : CampaignBookingPayloadFormatter::normalizeDeliverables($campaign->deliverables),
                'campaign_slot_id' => $slot->id,
            ]);

            return $slot;
        });
    }
}
