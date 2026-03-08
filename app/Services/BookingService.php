<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            // Validate required fields
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

    public function validateBookingAuth(User $creator, ?User $authUser): bool
    {
        if (! $authUser) {
            return true; // Guest user
        }

        if ($authUser->id === $creator->id) {
            return false; // Creator cannot book themselves
        }

        if (! $this->isBrandUser($authUser)) {
            return false; // Only brand users can make bookings
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
