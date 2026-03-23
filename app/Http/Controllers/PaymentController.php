<?php

namespace App\Http\Controllers;

use App\Models\BookingPayment;
use App\Services\PaymentService;
use App\Support\ClaimAccountResetUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function success(Request $request): View|RedirectResponse
    {
        try {
            $sessionId = $request->query('session_id');
            $reference = $request->query('reference');

            if (! $sessionId && ! $reference && ! $request->session()->has('message') && ! $request->session()->has('success')) {
                return redirect()->route('home')->with('error', 'Invalid payment session.');
            }

            if ($sessionId) {
                $this->paymentService->handleSuccessfulPayment($sessionId);
            }

            $claimAccount = $this->resolveClaimAccountDetails(
                sessionId: is_string($sessionId) ? $sessionId : null,
                reference: is_string($reference) ? $reference : null,
            );

            return view('payment.success', [
                'success' => $request->session()->get('success', $request->session()->get('message', 'Payment completed successfully!')),
                'claim_account_url' => Arr::get($claimAccount, 'url'),
                'claim_account_email' => Arr::get($claimAccount, 'email'),
            ]);
        } catch (\Exception $e) {
            Log::error('Payment success handling failed', [
                'session_id' => $request->query('session_id'),
                'reference' => $request->query('reference'),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('home')->with('error', 'Payment processing failed. Please contact support.');
        }
    }

    public function cancel(Request $request)
    {
        return view('payment.cancel')->with('message', 'Payment was cancelled. You can try again anytime.');
    }

    /**
     * @return array{url: string, email: string}|null
     */
    private function resolveClaimAccountDetails(?string $sessionId = null, ?string $reference = null): ?array
    {
        if (! $sessionId && ! $reference) {
            return null;
        }

        $payment = BookingPayment::query()
            ->with(['booking.brandUser'])
            ->where('status', 'completed')
            ->where(function ($query) use ($sessionId, $reference): void {
                if ($sessionId) {
                    $query->where('session_id', $sessionId);
                }

                if ($reference) {
                    $sessionId
                        ? $query->orWhere('provider_reference', $reference)
                        : $query->where('provider_reference', $reference);
                }
            })
            ->latest()
            ->first();

        if (! $payment || ! $payment->booking) {
            return null;
        }

        $booking = $payment->booking;
        $claimUser = $booking->brandUser;

        if (
            ! $claimUser ||
            ! $booking->guest_email ||
            ! $booking->guest_name ||
            $booking->account_claimed ||
            $claimUser->email !== $booking->guest_email
        ) {
            return null;
        }

        $url = ClaimAccountResetUrl::resolveFor($claimUser, $booking);

        return [
            'url' => $url,
            'email' => $claimUser->email,
        ];
    }
}
