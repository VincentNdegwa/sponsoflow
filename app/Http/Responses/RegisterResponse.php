<?php

namespace App\Http\Responses;

use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = auth()->user();
        $workspace = $user->workspaces->first();
        
        $workspaceType = $workspace?->type === 'creator' ? 'Creator' : 'Brand';
        
        Toast::success(
            'Welcome to SponsorFlow!',
            "Your {$workspaceType} workspace has been created successfully."
        );

        return redirect()->intended(route('dashboard'));
    }
}
