<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\BookingInquiryToken;
use App\Models\BookingReviewToken;
use App\Models\BookingSubmission;
use App\Models\Product;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\DisputeOpenedNotification;
use App\Notifications\InquiryApprovedNotification;
use App\Notifications\InquiryCounteredNotification;
use App\Notifications\InquiryReceivedNotification;
use App\Notifications\InquiryRejectedNotification;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\WorkApprovedNotification;
use App\Notifications\WorkSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BookingService
{
    public function __construct(protected PaymentService $paymentService) {}

    public function createInquiry(array $data): array
    {
        try {
            $creator = $data['creator'];
            $workspace = $creator->currentWorkspace();
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
            $totalAmount = $data['requirement_data']['budget'] ?? 0;
            $isGuest = ! isset($data['brand_user_id']);
            $bookingData = [
                'slot_id' => null,
                'product_id' => $product->id,
                'creator_id' => $creator->id,
                'workspace_id' => $workspace->id,
                'type' => BookingType::INQUIRY,
                'requirement_data' => $data['requirement_data'],
                'amount_paid' => $totalAmount,
                'status' => BookingStatus::INQUIRY,
                'notes' => 'Custom collaboration proposal submitted',
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
            $workspace = $creator->currentWorkspace();
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

        $booking->update(['status' => BookingStatus::PROCESSING]);

        $token = BookingInquiryToken::generateFor($booking, 'respond');
        $checkoutUrl = route('bookings.inquiry-respond', ['token' => $token->token]);

        $notifiable = new \Illuminate\Notifications\AnonymousNotifiable;
        $notifiable->route('mail', $booking->guest_email);
        \Illuminate\Support\Facades\Notification::send(
            [$notifiable],
            new InquiryApprovedNotification($booking, $checkoutUrl),
        );

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

        $notifiable = new \Illuminate\Notifications\AnonymousNotifiable;
        $notifiable->route('mail', $booking->guest_email);
        \Illuminate\Support\Facades\Notification::send(
            [$notifiable],
            new InquiryRejectedNotification($booking),
        );

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

        $token = BookingInquiryToken::generateFor($booking, 'accept_counter');
        $respondUrl = route('bookings.inquiry-respond', ['token' => $token->token]);

        $notifiable = new \Illuminate\Notifications\AnonymousNotifiable;
        $notifiable->route('mail', $booking->guest_email);
        \Illuminate\Support\Facades\Notification::send(
            [$notifiable],
            new InquiryCounteredNotification($booking, $respondUrl),
        );

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
            : [BookingStatus::PROCESSING];

        if (! in_array($booking->status, $allowedStatuses, true)) {
            return $this->errorResponse('This booking cannot be fulfilled at this time.');
        }

        return DB::transaction(function () use ($booking, $requirementData, $acceptingCounter) {
            $updateData = [
                'status' => BookingStatus::PENDING_PAYMENT,
                'requirement_data' => $requirementData,
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

        // $released = $this->paymentService->releaseFunds($booking);

        // if (! $released) {
        //     return $this->errorResponse('Failed to release funds. Please try again.');
        // }

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
        // if ($booking->isGuestBooking()) {
        $token = BookingReviewToken::generateFor($booking);
        $reviewUrl = route('bookings.guest-review', ['token' => $token->token]);

        $guestNotifiable = new \Illuminate\Notifications\AnonymousNotifiable;
        $guestNotifiable->route('mail', $booking->guest_email);
        Notification::send([$guestNotifiable], new WorkSubmittedNotification($booking, $submission, $reviewUrl));
        // } else {
        //     $booking->brandUser->notify(new WorkSubmittedNotification($booking, $submission));
        // }
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
}
