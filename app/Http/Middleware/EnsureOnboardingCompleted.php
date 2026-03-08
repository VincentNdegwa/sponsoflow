<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || $request->is('api/*')) {
            return $next($request);
        }

        if ($request->is('settings/*') || $request->is('onboarding/*')) {
            return $next($request);
        }

        $user = Auth::user();
        $workspace = currentWorkspace();

        if ($workspace && $workspace->needsOnboarding()) {
            app()->bind('needs.onboarding', function () use ($workspace) {
                return $workspace;
            });
        }

        return $next($request);
    }
}
