<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin?->isSystemAdmin()) {
            abort(403);
        }

        if ($user->isSystemAdmin() || $user->id === $admin->id) {
            return back()->withErrors(['impersonation' => __('You cannot impersonate this user.')]);
        }

        $request->session()->put('impersonation.admin_id', $admin->id);
        $request->session()->put('impersonation.user_id', $user->id);
        $request->session()->put('impersonation.started_at', now()->toISOString());

        Auth::guard('web')->login($user);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin?->isSystemAdmin()) {
            abort(403);
        }

        $request->session()->forget(['impersonation.admin_id', 'impersonation.user_id', 'impersonation.started_at']);

        Auth::guard('web')->logout();

        return redirect()->route('admin.users');
    }
}
