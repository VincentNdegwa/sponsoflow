<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicBookingController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function reserve(Request $request, User $user)
    {
        $request->validate([
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:slots,id',
        ]);

        $slots = Slot::whereIn('id', $request->slot_ids)
            ->where('status', \App\Enums\SlotStatus::Available)
            ->whereHas('product', function ($q) use ($user) {
                $q->where('workspace_id', $user->currentWorkspace()->id)
                    ->where('is_public', true)
                    ->where('is_active', true);
            })
            ->notReserved()
            ->get();

        if ($slots->count() !== count($request->slot_ids)) {
            return response()->json(['error' => 'Some slots are no longer available'], 422);
        }

        $sessionId = session()->getId();

        DB::transaction(function () use ($slots, $sessionId) {
            foreach ($slots as $slot) {
                $slot->reserveFor($sessionId);
            }
        });

        return response()->json(['success' => true]);
    }

    public function checkout(Request $request, User $user)
    {
        $request->validate([
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:slots,id',
            'product_id' => 'nullable|exists:products,id', // For inquiries when no slots selected
            'guest_data' => 'required|array',
            'guest_data.name' => 'required|string|max:255',
            'guest_data.email' => 'required|email|max:255',
            'guest_data.company' => 'nullable|string|max:255',
            'requirement_data' => 'required|array',
            'booking_type' => 'required|in:instant,inquiry',
        ]);

        $workspace = $user->currentWorkspace();

        // Handle inquiries (no specific slots)
        if ($request->booking_type === 'inquiry') {
            $productId = $request->product_id ?? (! empty($request->slot_ids) ? Slot::find($request->slot_ids[0])->product_id : null);
            
            if (! $productId) {
                return response()->json(['error' => 'Product ID is required for inquiries'], 422);
            }

            $product = Product::where('id', $productId)
                ->where('workspace_id', $workspace->id)
                ->where('is_public', true)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                return response()->json(['error' => 'Product not found or not available'], 422);
            }

            $totalAmount = $request->requirement_data['budget'] ?? 0;

            try {
                $booking = Booking::create([
                    'slot_id' => null, // No specific slot for inquiries
                    'product_id' => $product->id,
                    'creator_id' => $user->id,
                    'brand_user_id' => null,
                    'brand_workspace_id' => null,
                    'type' => BookingType::INQUIRY,
                    'guest_email' => $request->guest_data['email'],
                    'guest_name' => $request->guest_data['name'],
                    'guest_company' => $request->guest_data['company'] ?? '',
                    'requirement_data' => $request->requirement_data,
                    'amount_paid' => $totalAmount,
                    'status' => BookingStatus::INQUIRY,
                    'notes' => 'Custom collaboration proposal submitted by brand',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Your inquiry has been submitted successfully!',
                    'booking_id' => $booking->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Inquiry creation failed', [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'Failed to submit inquiry. Please try again.'], 500);
            }
        }

        // Handle instant bookings (with slots)
        if (empty($request->slot_ids)) {
            return response()->json(['error' => 'Slot selection required for instant bookings'], 422);
        }

        $slots = Slot::whereIn('id', $request->slot_ids)
            ->with('product.requirements')
            ->where('status', \App\Enums\SlotStatus::Available)
            ->whereHas('product', function ($q) use ($user) {
                $q->where('workspace_id', $user->currentWorkspace()->id)
                    ->where('is_public', true)
                    ->where('is_active', true);
            })
            ->get();

        if ($slots->isEmpty()) {
            return response()->json(['error' => 'No available slots found'], 422);
        }

        $product = $slots->first()->product;
        $totalAmount = $slots->sum('price');

        // Check if workspace can receive payments for instant bookings
        if (! $workspace->canReceivePayments()) {
            return response()->json(['error' => 'Payment processing not available for this creator'], 422);
        }

        // Validate required fields for instant bookings
        foreach ($product->requirements->where('is_required', true) as $requirement) {
            if (empty($request->requirement_data[$requirement->id])) {
                return response()->json([
                    'error' => "Required field '{$requirement->name}' is missing",
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($request, $slots, $product, $user, $totalAmount) {
                // Create booking record for instant booking
                $booking = Booking::create([
                    'slot_id' => $slots->first()->id, // Primary slot for the booking
                    'product_id' => $product->id,
                    'creator_id' => $user->id,
                    'brand_user_id' => null, // Guest booking
                    'brand_workspace_id' => null,
                    'type' => BookingType::INSTANT,
                    'guest_email' => $request->guest_data['email'],
                    'guest_name' => $request->guest_data['name'],
                    'guest_company' => $request->guest_data['company'] ?? '',
                    'requirement_data' => $request->requirement_data,
                    'amount_paid' => $totalAmount,
                    'status' => BookingStatus::PENDING_PAYMENT,
                ]);

                // Create checkout session
                $checkoutSession = $this->paymentService->createCheckoutSession($booking);

                // Update booking with checkout session ID
                $booking->update([
                    'stripe_session_id' => $checkoutSession['id'],
                ]);

                // Mark slots as reserved
                foreach ($slots as $slot) {
                    $slot->update([
                        'status' => \App\Enums\SlotStatus::Reserved,
                        'reserved_until' => now()->addMinutes(30),
                    ]);
                }

                session()->flash('checkout_url', $checkoutSession['url']);
                session()->flash('booking_id', $booking->id);
            });

            return response()->json([
                'checkout_url' => session('checkout_url'),
                'booking_id' => session('booking_id'),
            ]);

        } catch (\Exception $e) {
            Log::error('Booking creation failed', [
                'user_id' => $user->id,
                'slot_ids' => $request->slot_ids,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Booking failed. Please try again.'], 500);
        }
    }

    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');

        if (! $sessionId) {
            return redirect()->route('home')->with('error', 'Invalid session');
        }

        // Handle successful payment through our PaymentService
        try {
            $this->paymentService->handleSuccessfulPayment($sessionId);
        } catch (\Exception $e) {
            Log::error('Payment success handling failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return view('public.booking.success', compact('sessionId'));
    }

    public function cancel()
    {
        return view('public.booking.cancel');
    }
}
