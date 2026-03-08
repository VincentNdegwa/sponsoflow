<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StripeConnectController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function create(Request $request, Workspace $workspace)
    {
        try {
            if (! Auth::user()->isOwnerOf($workspace)) {
                return redirect()->back()->with('error', 'You do not have permission to set up payments for this workspace.');
            }

            $result = $this->paymentService->createConnectAccount($workspace);

            return redirect($result['onboarding_url']);
        } catch (\Exception $e) {
            Log::error('Stripe Connect account creation failed', [
                'workspace_id' => $workspace->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to set up payment account. Please try again.');
        }
    }

    public function return(Request $request)
    {
        return redirect()->route('dashboard')->with('success', 'Payment account setup completed! You can now accept payments.');
    }

    public function refresh(Request $request)
    {
        return redirect()->route('dashboard')->with('info', 'Please complete your payment account setup to start accepting payments.');
    }

    public function checkStatus(Request $request, Workspace $workspace)
    {
        try {
            $config = $workspace->activePaymentConfiguration('stripe');

            if (! $config) {
                return response()->json(['verified' => false, 'message' => 'No payment configuration found']);
            }

            $isVerified = $this->paymentService->isAccountVerified($config);

            return response()->json([
                'verified' => $isVerified,
                'onboarding_url' => $isVerified ? null : $this->paymentService->getOnboardingUrl($config),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check Stripe Connect status', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['verified' => false, 'message' => 'Failed to check verification status']);
        }
    }
}
