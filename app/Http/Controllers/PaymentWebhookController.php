<?php

namespace App\Http\Controllers;

use App\Models\BookingPayment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * Handle Stripe webhooks
     */
    public function stripeWebhook(Request $request)
    {
        try {
            // Verify webhook signature here in production

            $event = $request->all();

            switch ($event['type']) {
                case 'checkout.session.completed':
                    $this->paymentService->handleSuccessfulPayment(
                        $event['data']['object']['id'],
                        'stripe'
                    );
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event['type']]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to process webhook'], 400);
        }
    }

    /**
     * Handle Paystack webhooks
     */
    public function paystackWebhook(Request $request)
    {
        try {
            // Verify webhook signature with Paystack secret in production

            $event = $request->all();

            switch ($event['event']) {
                case 'charge.success':
                    $this->paymentService->handleSuccessfulPayment(
                        $event['data']['reference'],
                        'paystack'
                    );
                    break;

                default:
                    Log::info('Unhandled Paystack webhook event', ['type' => $event['event']]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Paystack webhook error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to process webhook'], 400);
        }
    }

    /**
     * Handle payment success callback (for Paystack redirects)
     */
    public function paystackCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('payment.cancel')->with('error', 'Payment verification failed');
        }

        try {
            $this->paymentService->handleSuccessfulPayment($reference, 'paystack');

            return redirect()->route('payment.success', ['reference' => $reference])
                ->with('message', 'Payment successful!');
        } catch (\Exception $e) {
            Log::error('Paystack callback error', ['error' => $e->getMessage()]);

            $completedPayment = BookingPayment::query()
                ->where('provider', 'paystack')
                ->where('provider_reference', $reference)
                ->where('status', 'completed')
                ->exists();

            if ($completedPayment) {
                return redirect()->route('payment.success', ['reference' => $reference])
                    ->with('message', 'Payment successful!');
            }

            return redirect()->route('payment.cancel')->with('error', 'Payment processing failed');
        }
    }

    /**
     * Release funds from escrow to creator
     */
    public function releaseFunds(Request $request, int $bookingId)
    {
        try {
            $booking = \App\Models\Booking::findOrFail($bookingId);

            // Add authorization logic here (ensure only admin or brand can release)

            $success = $this->paymentService->releaseFunds($booking);

            if ($success) {
                return response()->json([
                    'message' => 'Funds released successfully',
                    'booking_id' => $bookingId,
                ]);
            }

            return response()->json(['error' => 'Failed to release funds'], 400);
        } catch (\Exception $e) {
            Log::error('Fund release error', ['error' => $e->getMessage(), 'booking_id' => $bookingId]);

            return response()->json(['error' => 'Failed to release funds'], 500);
        }
    }

    /**
     * Process refund for booking
     */
    public function refundPayment(Request $request, int $bookingId)
    {
        try {
            $booking = \App\Models\Booking::findOrFail($bookingId);
            $reason = $request->input('reason', 'Work rejected');

            // Add authorization logic here

            $success = $this->paymentService->refundPayment($booking, $reason);

            if ($success) {
                return response()->json([
                    'message' => 'Payment refunded successfully',
                    'booking_id' => $bookingId,
                ]);
            }

            return response()->json(['error' => 'Failed to process refund'], 400);
        } catch (\Exception $e) {
            Log::error('Refund error', ['error' => $e->getMessage(), 'booking_id' => $bookingId]);

            return response()->json(['error' => 'Failed to process refund'], 500);
        }
    }

    /**
     * Get supported banks for Paystack
     */
    public function getSupportedBanks()
    {
        try {
            $banks = $this->paymentService->getSupportedBanks('paystack');

            return response()->json(['banks' => $banks]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch banks', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to fetch banks'], 500);
        }
    }

    /**
     * Verify bank account details for Paystack
     */
    public function verifyBankAccount(Request $request)
    {
        try {
            $request->validate([
                'account_number' => 'required|string',
                'bank_code' => 'required|string',
            ]);

            $accountDetails = $this->paymentService->verifyBankAccount(
                $request->account_number,
                $request->bank_code,
                'paystack'
            );

            return response()->json(['account_details' => $accountDetails]);
        } catch (\Exception $e) {
            Log::error('Bank verification error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to verify account'], 400);
        }
    }
}
