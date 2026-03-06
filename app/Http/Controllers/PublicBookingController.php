<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class PublicBookingController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

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
            'guest_data' => 'required|array',
            'guest_data.name' => 'required|string|max:255',
            'guest_data.email' => 'required|email|max:255',
            'guest_data.company' => 'nullable|string|max:255',
            'requirement_data' => 'required|array',
        ]);

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

        foreach ($product->requirements->where('is_required', true) as $requirement) {
            if (empty($request->requirement_data[$requirement->id])) {
                return response()->json([
                    'error' => "Required field '{$requirement->name}' is missing",
                ], 422);
            }
        }

        try {
            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'customer_email' => $request->guest_data['email'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => $product->name.' - '.$slots->count().' slot(s)',
                                'description' => 'Booking with '.$user->name,
                            ],
                            'unit_amount' => $totalAmount * 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => route('booking.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('booking.cancel'),
                'metadata' => [
                    'creator_id' => $user->id,
                    'slot_ids' => implode(',', $request->slot_ids),
                    'guest_name' => $request->guest_data['name'],
                    'guest_email' => $request->guest_data['email'],
                    'guest_company' => $request->guest_data['company'] ?? '',
                    'requirement_data' => json_encode($request->requirement_data),
                ],
            ]);

            foreach ($slots as $slot) {
                $slot->update(['stripe_session_id' => $session->id]);
            }

            return response()->json(['checkout_url' => $session->url]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment setup failed'], 500);
        }
    }

    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');

        if (! $sessionId) {
            return redirect()->route('home')->with('error', 'Invalid session');
        }

        return view('public.booking.success', compact('sessionId'));
    }

    public function cancel()
    {
        return view('public.booking.cancel');
    }
}
