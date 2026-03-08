<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function success(Request $request)
    {
        try {
            $sessionId = $request->query('session_id');

            if (! $sessionId) {
                return redirect()->route('home')->with('error', 'Invalid payment session.');
            }

            $this->paymentService->handleSuccessfulPayment($sessionId);

            return view('payment.success')->with('success', 'Payment completed successfully!');
        } catch (\Exception $e) {
            Log::error('Payment success handling failed', [
                'session_id' => $request->query('session_id'),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('home')->with('error', 'Payment processing failed. Please contact support.');
        }
    }

    public function cancel(Request $request)
    {
        return view('payment.cancel')->with('message', 'Payment was cancelled. You can try again anytime.');
    }
}
