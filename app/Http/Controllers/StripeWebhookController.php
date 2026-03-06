<?php

namespace App\Http\Controllers;

use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('stripe-signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid webhook signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event->data->object);
        }

        return response()->json(['status' => 'success']);
    }

    private function handleCheckoutCompleted($session)
    {
        DB::transaction(function () use ($session) {
            $slotIds = explode(',', $session->metadata->slot_ids);
            $slots = Slot::whereIn('id', $slotIds)->with('product')->get();

            if ($slots->isEmpty()) {
                return;
            }

            $creator = User::find($session->metadata->creator_id);
            $product = $slots->first()->product;
            $guestEmail = $session->metadata->guest_email;

            $brandUser = User::where('email', $guestEmail)->first();
            $brandWorkspace = null;

            if (! $brandUser) {
                $brandUser = User::create([
                    'name' => $session->metadata->guest_name,
                    'email' => $guestEmail,
                    'password' => null,
                ]);

                $brandWorkspace = Workspace::create([
                    'name' => ($session->metadata->guest_company ?: $session->metadata->guest_name).' Workspace',
                    'type' => 'brand',
                ]);

                $brandUser->addRole('owner', $brandWorkspace);
            } else {
                $brandWorkspace = $brandUser->currentWorkspace();
                if (! $brandWorkspace) {
                    $brandWorkspace = Workspace::create([
                        'name' => ($session->metadata->guest_company ?: $session->metadata->guest_name).' Workspace',
                        'type' => 'brand',
                    ]);
                    $brandUser->addRole('owner', $brandWorkspace);
                }
            }

            foreach ($slots as $slot) {
                $slot->update([
                    'status' => SlotStatus::Booked,
                    'booked_by_user_id' => $brandUser->id,
                    'reserved_at' => null,
                    'reserved_by' => null,
                ]);

                Booking::create([
                    'slot_id' => $slot->id,
                    'product_id' => $product->id,
                    'creator_id' => $creator->id,
                    'brand_user_id' => $brandUser->id,
                    'brand_workspace_id' => $brandWorkspace->id,
                    'guest_email' => $guestEmail,
                    'guest_name' => $session->metadata->guest_name,
                    'guest_company' => $session->metadata->guest_company,
                    'requirement_data' => json_decode($session->metadata->requirement_data, true),
                    'amount_paid' => $session->amount_total / 100,
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'stripe_session_id' => $session->id,
                    'status' => 'confirmed',
                ]);
            }

            $product->incrementSoldCount();

            $this->sendWelcomeEmail($brandUser, $creator, $slots->count());
        });
    }

    private function sendWelcomeEmail(User $brandUser, User $creator, int $slotCount)
    {
        $magicLink = $this->generateMagicLink($brandUser);

    }

    private function generateMagicLink(User $user): string
    {
        $token = Str::random(60);

        cache()->put("magic_link:{$token}", $user->id, now()->addHours(24));

        return route('auth.magic-login', ['token' => $token]);
    }
}
