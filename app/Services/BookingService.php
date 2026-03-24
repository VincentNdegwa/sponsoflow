<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\CampaignSlotStatus;
use App\Models\Booking;
use App\Models\BookingInquiryToken;
use App\Models\BookingInviteToken;
use App\Models\BookingReviewToken;
use App\Models\BookingSubmission;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\Slot;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BookingInviteNotification;
use App\Notifications\DisputeOpenedNotification;
use App\Notifications\InquiryApprovedNotification;
use App\Notifications\InquiryCounteredNotification;
use App\Notifications\InquiryReceivedNotification;
use App\Notifications\InquiryRejectedNotification;
use App\Notifications\MarketplaceBookingApprovedNotification;
use App\Notifications\MarketplaceBookingRejectedNotification;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\WorkApprovedNotification;
use App\Notifications\WorkSubmittedNotification;
use App\Support\CampaignBookingPayloadFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BookingService
{
    public function __construct(
        protected PaymentService $paymentService,
        protected BookingShadowCampaignService $bookingShadowCampaignService,
    ) {}

    public function validateRequirementData(Product $product, array $data): array
    {
        $errors = [];

        foreach ($product->requirements->where('is_required', true) as $requirement) {
            if (empty($data[$requirement->id])) {
                $errors["requirementData.{$requirement->id}"] = 'This field is required.';
            }
        }

        return $errors;
    }

    public function createInquiry(array $data): array
    {
        try {
            $creator = $data['creator'];
            $workspace = $data['workspace'] ?? $creator->currentWorkspace();
            $productId = $data['product_id'];

            if (! $productId) {
                return $this->errorResponse('Product ID is required for inquiries');
            }

            $product = Product::where('id', $productId)
                ->where('workspace_id', $workspace->id)
                ->where('is_public', true)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                return $this->errorResponse('Product not found or not available');
            }

            $inputData = is_array($data['requirement_data'] ?? null)
                ? $data['requirement_data']
                : [];

            $requirementData = CampaignBookingPayloadFormatter::extractProductRequirements($inputData);
            $campaignDetails = [];
            $campaignDeliverables = [];

            $isGuest = ! isset($data['brand_user_id']);

            if (! $isGuest) {
                if (empty($data['brand_workspace_id'])) {
                    return $this->errorResponse('Brand workspace is required for authenticated inquiries.');
                }

                $campaignMode = (string) ($data['campaign_mode'] ?? 'new');
                if ($campaignMode !== 'new' && $campaignMode !== 'existing') {
                    return $this->errorResponse('Invalid campaign mode selected.');
                }

                if ($campaignMode === 'existing') {
                    $campaignId = (int) ($data['campaign_id'] ?? 0);
                    if ($campaignId <= 0) {
                        return $this->errorResponse('Please select an existing campaign.');
                    }

                    $campaign = Campaign::query()
                        ->whereKey($campaignId)
                        ->where('workspace_id', $data['brand_workspace_id'])
                        ->first();

                    if (! $campaign) {
                        return $this->errorResponse('Selected campaign is invalid for this workspace.');
                    }

                    $campaignDetails = CampaignBookingPayloadFormatter::fromCampaign($campaign);
                    $campaignDeliverables = CampaignBookingPayloadFormatter::normalizeDeliverables($campaign->deliverables);
                } else {
                    $campaignBudget = (float) data_get($inputData, 'budget', 0);
                    $campaignDetails = CampaignBookingPayloadFormatter::fromInquiryInput(
                        creatorName: (string) ($creator->name ?? 'creator'),
                        budget: $campaignBudget,
                        input: $inputData,
                    );
                }
            } else {
                $campaignBudget = (float) data_get($inputData, 'budget', 0);
                $campaignDetails = CampaignBookingPayloadFormatter::fromInquiryInput(
                    creatorName: (string) ($creator->name ?? 'creator'),
                    budget: $campaignBudget,
                    input: $inputData,
                );
            }

            $totalAmount = (float) data_get($campaignDetails, 'meta.total_budget', 0);

            $bookingData = [
                'slot_id' => null,
                'product_id' => $product->id,
                'creator_id' => $creator->id,
                'workspace_id' => $workspace->id,
                'type' => BookingType::INQUIRY,
                'requirement_data' => $requirementData,
                'campaign_details' => $campaignDetails,
                'campaign_deliverables' => $campaignDeliverables,
                'amount_paid' => $totalAmount,
                'currency' => $workspace->currency ?? 'USD',
                'status' => BookingStatus::INQUIRY,
                'notes' => 'Custom collaboration proposal submitted',
            ];

            if ($isGuest) {
                data_set($bookingData, 'requirement_data.guest_brand_profile', [
                    'name' => $data['guest_data']['name'] ?? null,
                    'email' => $data['guest_data']['email'] ?? null,
                    'company' => $data['guest_data']['company'] ?? null,
                ]);

                $bookingData['brand_user_id'] = null;
                $bookingData['brand_workspace_id'] = null;
                $bookingData['guest_email'] = $data['guest_data']['email'];
                $bookingData['guest_name'] = $data['guest_data']['name'];
                $bookingData['guest_company'] = $data['guest_data']['company'] ?? '';
            } else {
                $bookingData['brand_user_id'] = $data['brand_user_id'];
                $bookingData['brand_workspace_id'] = $data['brand_workspace_id'];
                $bookingData['guest_email'] = null;
                $bookingData['guest_name'] = null;
                $bookingData['guest_company'] = null;
            }

            $booking = Booking::create($bookingData);

            $slot = $this->bookingShadowCampaignService->provisionForInquiry($booking->fresh());
            if ($slot && ! $booking->campaign_slot_id) {
                $booking->update(['campaign_slot_id' => $slot->id]);
            }

            $booking->creator->notify(new InquiryReceivedNotification($booking));

            return $this->successResponse([
                'message' => 'Your inquiry has been submitted successfully!',
                'booking_id' => $booking->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Inquiry creation failed', [
                'creator_id' => $data['creator']->id ?? null,
                'product_id' => $data['product_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to submit inquiry. Please try again.');
        }
    }

    public function createInstantBooking(array $data): array
    {
        try {
            $creator = $data['creator'];
            $workspace = $data['workspace'] ?? $creator->currentWorkspace();
            $slotIds = $data['slot_ids'];
            $isGuest = ! isset($data['brand_user_id']);

            if (empty($slotIds)) {
                return $this->errorResponse('Slot selection required for instant bookings');
            }

            $slots = Slot::whereIn('id', $slotIds)
                ->with('product.requirements')
                ->where('status', \App\Enums\SlotStatus::Available)
                ->whereHas('product', function ($q) use ($workspace) {
                    $q->where('workspace_id', $workspace->id)
                        ->where('is_public', true)
                        ->where('is_active', true);
                })
                ->get();

            if ($slots->isEmpty()) {
                return $this->errorResponse('No available slots found');
            }

            $product = $slots->first()->product;
            $totalAmount = $slots->sum('price');

            if (! $workspace->canReceivePayments('paystack')) {
                return $this->errorResponse('Payment processing not available for this creator');
            }

            foreach ($product->requirements->where('is_required', true) as $requirement) {
                if (empty($data['requirement_data'][$requirement->id])) {
                    return $this->errorResponse("Required field '{$requirement->name}' is missing");
                }
            }

            return DB::transaction(function () use ($data, $slots, $product, $creator, $workspace, $totalAmount, $isGuest) {
                $bookingData = [
                    'slot_id' => $slots->first()->id,
                    'product_id' => $product->id,
                    'creator_id' => $creator->id,
                    'workspace_id' => $workspace->id,
                    'type' => BookingType::INSTANT,
                    'requirement_data' => $data['requirement_data'],
                    'amount_paid' => $totalAmount,
                    'currency' => $workspace->currency ?? 'USD',
                    'status' => BookingStatus::PENDING_PAYMENT,
                ];

                if ($isGuest) {
                    $bookingData['brand_user_id'] = null;
                    $bookingData['brand_workspace_id'] = null;
                    $bookingData['guest_email'] = $data['guest_data']['email'];
                    $bookingData['guest_name'] = $data['guest_data']['name'];
                    $bookingData['guest_company'] = $data['guest_data']['company'] ?? '';
                } else {
                    $bookingData['brand_user_id'] = $data['brand_user_id'];
                    $bookingData['brand_workspace_id'] = $data['brand_workspace_id'];
                    $bookingData['guest_email'] = null;
                    $bookingData['guest_name'] = null;
                    $bookingData['guest_company'] = null;
                }

                $booking = Booking::create($bookingData);

                $checkoutSession = $this->paymentService->createCheckoutSession($booking);

                $booking->update([
                    'stripe_session_id' => $checkoutSession['id'],
                ]);

                foreach ($slots as $slot) {
                    $slot->update([
                        'status' => \App\Enums\SlotStatus::Reserved,
                        'reserved_until' => now()->addMinutes(30),
                    ]);
                }

                return $this->successResponse([
                    'checkout_url' => $checkoutSession['url'],
                    'booking_id' => $booking->id,
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Instant booking creation failed', [
                'creator_id' => $data['creator']->id ?? null,
                'slot_ids' => $data['slot_ids'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Booking failed. Please try again.');
        }
    }

    public function approveInquiry(Booking $booking): array
    {
        if (! $booking->canApproveInquiry()) {
            return $this->errorResponse('This inquiry cannot be approved at this time.');
        }

        $booking->update(['status' => BookingStatus::PENDING_PAYMENT]);

        $brandEmail = $this->resolveBrandEmail($booking);
        $token = BookingInquiryToken::generateFor($booking, 'respond', $brandEmail);
        $checkoutUrl = route('bookings.inquiry-respond', ['token' => $token->token]);

        $this->sendBrandNotification($booking, new InquiryApprovedNotification($booking, $checkoutUrl));

        return $this->successResponse(['checkout_url' => $checkoutUrl]);
    }

    public function rejectInquiry(Booking $booking, ?string $creatorNotes = null): array
    {
        if (! $booking->canRejectInquiry()) {
            return $this->errorResponse('This inquiry cannot be rejected at this time.');
        }

        $booking->update([
            'status' => BookingStatus::REJECTED,
            'creator_notes' => $creatorNotes,
        ]);

        if ($booking->campaignSlot) {
            $booking->campaignSlot->update([
                'status' => CampaignSlotStatus::Cancelled,
            ]);
        }

        $this->sendBrandNotification($booking, new InquiryRejectedNotification($booking));

        return $this->successResponse([]);
    }

    public function approveMarketplaceApplicationBooking(Booking $booking): array
    {
        if (! $booking->canCreatorApproveMarketplaceApplication()) {
            return $this->errorResponse('This marketplace booking cannot be approved at this time.');
        }

        $booking->update([
            'status' => BookingStatus::PENDING_PAYMENT,
            'creator_notes' => null,
        ]);

        $brandEmail = $this->resolveBrandEmail($booking);
        $token = BookingInquiryToken::generateFor($booking, 'respond', $brandEmail);
        $checkoutUrl = route('bookings.inquiry-respond', ['token' => $token->token]);

        $this->sendBrandNotification($booking, new MarketplaceBookingApprovedNotification($booking->fresh(), $checkoutUrl));

        return $this->successResponse(['checkout_url' => $checkoutUrl]);
    }

    public function rejectMarketplaceApplicationBooking(Booking $booking, ?string $creatorNotes = null): array
    {
        if (! $booking->canCreatorRejectMarketplaceApplication()) {
            return $this->errorResponse('This marketplace booking cannot be rejected at this time.');
        }

        $booking->update([
            'status' => BookingStatus::REJECTED,
            'creator_notes' => $creatorNotes,
        ]);

        if ($booking->campaignSlot) {
            $booking->campaignSlot->update([
                'status' => CampaignSlotStatus::Cancelled,
            ]);
        }

        $this->sendBrandNotification($booking, new MarketplaceBookingRejectedNotification($booking->fresh()));

        return $this->successResponse([]);
    }

    public function counterInquiry(Booking $booking, float $counterAmount, ?string $creatorNotes = null): array
    {
        if (! $booking->canCounterInquiry()) {
            return $this->errorResponse('This inquiry cannot be countered at this time.');
        }

        $booking->update([
            'status' => BookingStatus::COUNTER_OFFERED,
            'counter_amount' => $counterAmount,
            'creator_notes' => $creatorNotes,
        ]);

        $brandEmail = $this->resolveBrandEmail($booking);
        $token = BookingInquiryToken::generateFor($booking, 'accept_counter', $brandEmail);
        $respondUrl = route('bookings.inquiry-respond', ['token' => $token->token]);

        $this->sendBrandNotification($booking, new InquiryCounteredNotification($booking, $respondUrl));

        return $this->successResponse([]);
    }

    /**
     * Brand fulfils their part of an approved (or accepted counter-offer) inquiry:
     * saves campaign requirements, sets amount if accepting a counter, and creates a payment checkout session.
     */
    public function fulfillInquiryBooking(Booking $booking, array $requirementData, bool $acceptingCounter = false): array
    {
        $allowedStatuses = $acceptingCounter
            ? [BookingStatus::COUNTER_OFFERED]
            : [BookingStatus::PENDING_PAYMENT];

        if (! in_array($booking->status, $allowedStatuses, true)) {
            return $this->errorResponse('This booking cannot be fulfilled at this time.');
        }

        return DB::transaction(function () use ($booking, $requirementData, $acceptingCounter) {
            $existingRequirementData = is_array($booking->requirement_data)
                ? $booking->requirement_data
                : [];

            $updateData = [
                'status' => BookingStatus::PENDING_PAYMENT,
                'requirement_data' => array_merge($existingRequirementData, $requirementData),
            ];

            if ($acceptingCounter) {
                $updateData['amount_paid'] = $booking->counter_amount;
            }

            $booking->update($updateData);

            $checkoutSession = $this->paymentService->createCheckoutSession($booking);
            $booking->update(['stripe_session_id' => $checkoutSession['id']]);

            return $this->successResponse(['checkout_url' => $checkoutSession['url']]);
        });
    }

    public function submitWork(Booking $booking, ?string $workUrl = null, ?string $screenshotPath = null): array
    {
        if (! $booking->canSubmitWork()) {
            return $this->errorResponse('This booking cannot accept a work submission at this time.');
        }

        $submission = BookingSubmission::create([
            'booking_id' => $booking->id,
            'work_url' => $workUrl,
            'screenshot_path' => $screenshotPath,
            'revision_number' => $booking->revision_count,
            'auto_approve_at' => now()->addHours(72),
        ]);

        $autoApproveAt = now()->addHours(72);

        $booking->update([
            'status' => BookingStatus::PROCESSING,
            'auto_approve_at' => $autoApproveAt,
        ]);

        $this->notifyBrandWorkSubmitted($booking, $submission);

        return $this->successResponse(['submission_id' => $submission->id]);
    }

    public function approveWork(Booking $booking): array
    {
        if (! $booking->canApprove()) {
            return $this->errorResponse('This booking cannot be approved at this time.');
        }

        $booking->update([
            'status' => BookingStatus::COMPLETED,
            'auto_approve_at' => null,
        ]);

        $booking->creator->notify(new WorkApprovedNotification($booking));

        return $this->successResponse([]);
    }

    public function requestRevision(Booking $booking, string $notes): array
    {
        if (! $booking->canRequestRevision()) {
            return $this->errorResponse('No revisions remaining or booking is not awaiting approval.');
        }

        $submission = $booking->latestSubmission;

        $submission->update(['revision_notes' => $notes]);

        $booking->increment('revision_count');
        $booking->update([
            'status' => BookingStatus::REVISION_REQUESTED,
            'auto_approve_at' => null,
        ]);

        $booking->creator->notify(new RevisionRequestedNotification($booking->refresh(), $submission));

        return $this->successResponse([]);
    }

    public function openDispute(Booking $booking, string $reason): array
    {
        if (! $booking->canDispute()) {
            return $this->errorResponse('A dispute cannot be opened for this booking.');
        }

        $refunded = $this->paymentService->refundPayment($booking, $reason);

        if (! $refunded) {
            return $this->errorResponse('Failed to process refund. Please try again.');
        }

        $booking->update([
            'status' => BookingStatus::DISPUTED,
            'auto_approve_at' => null,
        ]);

        $booking->creator->notify(new DisputeOpenedNotification($booking, $reason));

        if ($booking->brandUser) {
            $booking->brandUser->notify(new DisputeOpenedNotification($booking, $reason));
        }

        return $this->successResponse([]);
    }

    private function notifyBrandWorkSubmitted(Booking $booking, BookingSubmission $submission): void
    {
        if ($booking->brandUser) {
            $booking->brandUser->notify(new WorkSubmittedNotification($booking, $submission));
        } else {
            $token = BookingReviewToken::generateFor($booking);
            $reviewUrl = route('bookings.guest-review', ['token' => $token->token]);

            $guestNotifiable = new \Illuminate\Notifications\AnonymousNotifiable;
            $guestNotifiable->route('mail', $booking->guest_email);
            Notification::send([$guestNotifiable], new WorkSubmittedNotification($booking, $submission, $reviewUrl));
        }
    }

    private function sendBrandNotification(Booking $booking, \Illuminate\Notifications\Notification $notification): void
    {
        if ($booking->brandUser) {
            $booking->brandUser->notify($notification);
        } elseif ($booking->guest_email) {
            $notifiable = new \Illuminate\Notifications\AnonymousNotifiable;
            $notifiable->route('mail', $booking->guest_email);
            Notification::send([$notifiable], $notification);
        }
    }

    private function resolveBrandEmail(Booking $booking): ?string
    {
        return $booking->brandUser?->email ?? $booking->guest_email;
    }

    public function validateBookingAuth(User $creator, ?User $authUser): bool
    {
        if (! $authUser) {
            return true;
        }
        if ($authUser->id === $creator->id) {
            return false;
        }
        if (! $this->isBrandUser($authUser)) {
            return false;
        }

        return true;
    }

    private function isBrandUser(User $user): bool
    {
        $workspace = $user->currentWorkspace();
        if ($workspace && $workspace->isBrand()) {
            return true;
        }

        return $user->hasRole(['brand-admin', 'brand-contributor']);
    }

    public function submitRating(Booking $booking, int $rating, array $tags = [], ?string $comment = null, ?string $guestEmail = null): array
    {
        if ($booking->workspace_id === null) {
            return $this->errorResponse('No creator workspace found for this booking.');
        }

        $existingRating = \App\Models\WorkspaceRating::where('booking_id', $booking->id)->first();

        if ($existingRating) {
            return $this->errorResponse('This booking has already been rated.');
        }

        $submission = $booking->latestSubmission;

        \App\Models\WorkspaceRating::create([
            'workspace_id' => $booking->workspace_id,
            'booking_id' => $booking->id,
            'booking_submission_id' => $submission?->id,
            'rating' => $rating,
            'tags' => $tags ?: null,
            'comment' => $comment ?: null,
            'rated_by_guest_email' => $guestEmail,
            'rated_by_user_id' => $booking->brand_user_id,
        ]);

        return $this->successResponse([]);
    }

    private function successResponse(array $data): array
    {
        return array_merge(['success' => true], $data);
    }

    private function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }

    public function createCreatorInitiatedBooking(array $data): array
    {
        try {
            $workspace = $data['creator_workspace'];
            $creator = $workspace->owner;

            $product = Product::where('id', $data['product_id'])
                ->where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->firstOrFail();

            $bookingData = [
                'product_id' => $product->id,
                'creator_id' => $creator->id,
                'workspace_id' => $workspace->id,
                'type' => BookingType::INSTANT,
                'amount_paid' => $data['amount'] ?? $product->base_price,
                'currency' => $workspace->currency ?? 'USD',
                'status' => BookingStatus::PENDING_PAYMENT,
                'notes' => $data['notes'] ?? null,
                'max_revisions' => 3,
                'requirement_data' => [],
            ];

            if (($data['brand_type'] ?? 'new') === 'existing' && ! empty($data['brand_workspace_id'])) {
                $brandWorkspace = Workspace::findOrFail($data['brand_workspace_id']);
                $bookingData['brand_workspace_id'] = $brandWorkspace->id;
                $bookingData['brand_user_id'] = $brandWorkspace->owner_id;
            } else {
                $bookingData['guest_email'] = $data['brand_email'] ?? null;
                $bookingData['guest_name'] = $data['brand_name'] ?? null;
                $bookingData['guest_company'] = $data['brand_company'] ?? null;
            }

            $booking = Booking::create($bookingData);

            $token = BookingInviteToken::generateFor($booking);
            $inviteUrl = route('bookings.invite', ['token' => $token->token]);

            if (! empty($bookingData['brand_user_id'])) {
                User::find($bookingData['brand_user_id'])?->notify(
                    new BookingInviteNotification($booking, $inviteUrl)
                );
            }

            return $this->successResponse([
                'booking_id' => $booking->id,
                'booking_uuid' => $booking->uuid,
                'invite_url' => $inviteUrl,
                'token' => $token->token,
            ]);
        } catch (\Exception $e) {
            Log::error('Creator-initiated booking failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to create booking. Please try again.');
        }
    }

    /**
     * Send the invite email for a creator-initiated booking to the brand's email address.
     */
    public function sendBookingInviteEmail(Booking $booking, string $email, string $inviteUrl): void
    {
        $notifiable = new \Illuminate\Notifications\AnonymousNotifiable;
        $notifiable->route('mail', $email);
        Notification::send([$notifiable], new BookingInviteNotification($booking, $inviteUrl));
    }

    /**
     * Brand fulfils a creator-initiated invite: fills requirements and proceeds to payment.
     */
    public function fulfillInviteBooking(BookingInviteToken $token, array $requirementData, ?array $brandData = null): array
    {
        $booking = $token->booking;

        if (! $booking->canPayViaInvite()) {
            return $this->errorResponse('This booking cannot be completed at this time.');
        }

        return DB::transaction(function () use ($booking, $requirementData, $brandData) {
            $updateData = [];

            if (! empty($requirementData)) {
                $updateData['requirement_data'] = $requirementData;
            }

            if ($brandData !== null) {
                if (! empty($brandData['brand_user_id'])) {
                    $updateData['brand_user_id'] = $brandData['brand_user_id'];
                    $updateData['brand_workspace_id'] = $brandData['brand_workspace_id'];
                    $updateData['guest_email'] = null;
                    $updateData['guest_name'] = null;
                    $updateData['guest_company'] = null;
                } else {
                    $updateData['guest_name'] = $brandData['guest_name'];
                    $updateData['guest_email'] = $brandData['guest_email'];
                    $updateData['guest_company'] = $brandData['guest_company'] ?? null;
                }
            }

            if (! empty($updateData)) {
                $booking->update($updateData);
            }

            $checkoutSession = $this->paymentService->createCheckoutSession($booking);
            $booking->update(['stripe_session_id' => $checkoutSession['id']]);

            return $this->successResponse(['checkout_url' => $checkoutSession['url']]);
        });
    }

    /**
     * Brand-initiated instant booking (brand selects creator's slot and pays directly).
     */
    public function createBrandInstantBooking(array $data): array
    {
        $creatorWorkspace = Workspace::findOrFail($data['creator_workspace_id']);
        $creator = $creatorWorkspace->owner;

        return $this->createInstantBooking([
            'creator' => $creator,
            'workspace' => $creatorWorkspace,
            'slot_ids' => $data['slot_ids'],
            'requirement_data' => $data['requirement_data'],
            'brand_user_id' => $data['brand_user_id'],
            'brand_workspace_id' => $data['brand_workspace_id'],
        ]);
    }

    /**
     * Brand-initiated inquiry (brand selects creator and sends an inquiry to review).
     */
    public function createBrandInquiry(array $data): array
    {
        $creatorWorkspace = Workspace::findOrFail($data['creator_workspace_id']);
        $creator = $creatorWorkspace->owner;

        $requirementData = is_array($data['requirement_data'] ?? null)
            ? $data['requirement_data']
            : [
                'budget' => $data['budget'] ?? null,
                'campaign_goals' => $data['campaign_goals'] ?? null,
                'pitch' => $data['pitch'] ?? null,
            ];

        return $this->createInquiry([
            'creator' => $creator,
            'workspace' => $creatorWorkspace,
            'product_id' => $data['product_id'],
            'requirement_data' => $requirementData,
            'campaign_mode' => $data['campaign_mode'] ?? 'new',
            'campaign_id' => $data['campaign_id'] ?? null,
            'brand_user_id' => $data['brand_user_id'],
            'brand_workspace_id' => $data['brand_workspace_id'],
        ]);
    }
}
